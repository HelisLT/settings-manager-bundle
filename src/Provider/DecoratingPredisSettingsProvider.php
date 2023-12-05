<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\RedisModificationTrait;
use Helis\SettingsManagerBundle\Provider\Traits\TagFilteringTrait;
use Helis\SettingsManagerBundle\Settings\Traits\DomainNameExtractTrait;
use Predis\Client;
use Predis\Pipeline\Pipeline;
use Symfony\Component\Serializer\SerializerInterface;

class DecoratingPredisSettingsProvider implements ModificationAwareSettingsProviderInterface
{
    use DomainNameExtractTrait;
    use RedisModificationTrait;
    use TagFilteringTrait;

    private const DOMAIN_KEY = 'domain';
    private const HASHMAP_KEY = 'hashmap';
    protected Client $redis;

    protected int $ttl = 604800;
    protected string $namespace = 'settings_provider_cache';

    public function __construct(
        protected SettingsProviderInterface $decoratingProvider,
        Client $redis,
        protected SerializerInterface $serializer
    ) {
        $this->redis = $redis;
    }

    public function setTtl(int $ttl)
    {
        $this->ttl = $ttl;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function isReadOnly(): bool
    {
        return $this->decoratingProvider->isReadOnly();
    }

    public function getSettings(array $domainNames): array
    {
        $this->buildHashmap();

        $result = $this->redis->pipeline(function (Pipeline $pipe) use ($domainNames) {
            foreach ($domainNames as $domainName) {
                $pipe->hgetall($this->getNamespacedKey($domainName));
            }
        });

        $out = [];

        foreach ($result as $domainGroup) {
            foreach ($domainGroup as $item) {
                if ($item !== null) {
                    $out[] = $this->serializer->deserialize($item, SettingModel::class, 'json');
                }
            }
        }

        return array_values(array_filter($out));
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $this->buildHashmap();

        $result = $this->redis->pipeline(
            function (Pipeline $pipe) use ($domainNames, $settingNames) {
                foreach ($domainNames as $domainName) {
                    $pipe->hmget($this->getNamespacedKey($domainName), $settingNames);
                }
            }
        );

        $out = [];

        foreach ($result as $domainGroup) {
            foreach ($domainGroup as $item) {
                if ($item !== null) {
                    $out[] = $this->serializer->deserialize($item, SettingModel::class, 'json');
                }
            }
        }

        return array_values(array_filter($out));
    }

    public function getSettingsByTag(array $domainNames, string $tagName): array
    {
        return $this->filterSettingsByTag($this->getSettings($domainNames), $tagName);
    }

    public function save(SettingModel $settingModel): bool
    {
        $output = $this->decoratingProvider->save($settingModel);

        // trigger domain warmup, in case of empty hashes
        $this->getDomains();
        $this->getDomains(true);

        $this->redis->pipeline(function (Pipeline $pipe) use ($settingModel) {
            $serializedDomain = $this->serializer->serialize($settingModel->getDomain(), 'json');
            $serializedSetting = $this->serializer->serialize($settingModel, 'json');

            $pipe->hsetnx($this->getDomainKey(false), $settingModel->getDomain()->getName(), $serializedDomain);
            if ($settingModel->getDomain()->isEnabled()) {
                $pipe->hsetnx($this->getDomainKey(true), $settingModel->getDomain()->getName(), $serializedDomain);
            }

            $pipe->hset(
                $this->getNamespacedKey($settingModel->getDomain()->getName()),
                $settingModel->getName(),
                $serializedSetting
            );
        });
        $this->setModificationTime();

        return $output;
    }

    public function delete(SettingModel $settingModel): bool
    {
        $output = $this->decoratingProvider->delete($settingModel);

        $this->redis->pipeline(function (Pipeline $pipe) use ($settingModel) {
            $pipe->hdel($this->getNamespacedKey($settingModel->getDomain()->getName()), [$settingModel->getName()]);
            $domainName = $settingModel->getDomain()->getName();
            $domainNames = $this->extractDomainNames($this->decoratingProvider->getDomains());
            if (!in_array($domainName, $domainNames)) {
                $pipe->hdel($this->getDomainKey(true), [$domainName]);
                $pipe->hdel($this->getDomainKey(false), [$domainName]);
            }
        });
        $this->setModificationTime();

        return $output;
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $key = $this->getDomainKey($onlyEnabled);
        $domains = $this->redis->hgetall($key);

        if ($domains) {
            foreach ($domains as &$domain) {
                $domain = $this->serializer->deserialize($domain, DomainModel::class, 'json');
            }

            return array_values($domains);
        }

        $domains = $this->decoratingProvider->getDomains($onlyEnabled);

        if ($domains !== []) {
            $dictionary = [];
            foreach ($domains as $domain) {
                $dictionary[$domain->getName()] = $this->serializer->serialize($domain, 'json');
            }
            $this->redis->hmset($key, $dictionary);
            $this->setModificationTime();
        }

        return $domains;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $output = $this->decoratingProvider->updateDomain($domainModel);

        $this->redis->pipeline(function (Pipeline $pipe) use ($domainModel) {
            $serializedDomain = $this->serializer->serialize($domainModel, 'json');
            $pipe->hset($this->getDomainKey(false), $domainModel->getName(), $serializedDomain);

            if ($domainModel->isEnabled()) {
                $pipe->hset($this->getDomainKey(true), $domainModel->getName(), $serializedDomain);
            } else {
                $pipe->hdel($this->getDomainKey(true), [$domainModel->getName()]);
            }
        });
        $this->setModificationTime();
        $this->buildHashmap(true, $domainModel->getName());

        return $output;
    }

    public function deleteDomain(string $domainName): bool
    {
        $output = $this->decoratingProvider->deleteDomain($domainName);

        $this->redis->pipeline(function (Pipeline $pipe) use ($domainName) {
            $pipe->del([$this->getNamespacedKey($domainName)]);
            $pipe->hdel($this->getDomainKey(true), [$domainName]);
            $pipe->hdel($this->getDomainKey(false), [$domainName]);
        });
        $this->setModificationTime();

        return $output;
    }

    private function getDomainKey(bool $onlyEnabled = false): string
    {
        return $this->getNamespacedKey(self::DOMAIN_KEY.($onlyEnabled ? '_oe' : ''));
    }

    private function getHashMapKey(): string
    {
        return $this->getNamespacedKey(self::HASHMAP_KEY);
    }

    private function getNamespacedKey(string $key): string
    {
        return sprintf('%s[%s]', $this->namespace, $key);
    }

    protected function buildHashmap(bool $force = false, string $domainName = null): void
    {
        $key = $this->getHashMapKey();
        $isBuilt = $this->redis->get($key);
        if ((int) $isBuilt === 1 && $force === false) {
            return;
        }

        $domains = $domainName !== null ? [$domainName] : $this->extractDomainNames($this->decoratingProvider->getDomains());

        $settings = $this->decoratingProvider->getSettings($domains);

        if ($settings !== []) {
            $this->redis->pipeline(function (Pipeline $pipe) use ($settings, $key, $isBuilt) {
                foreach ($settings as $setting) {
                    $pipe->hset(
                        $this->getNamespacedKey($setting->getDomain()->getName()),
                        $setting->getName(),
                        $this->serializer->serialize($setting, 'json')
                    );
                }

                if ($isBuilt === null || (int) $isBuilt === 0) {
                    $pipe->setex($key, $this->ttl, 1);
                }
            });
            $this->setModificationTime();
        } elseif ($isBuilt === null || (int) $isBuilt === 0) {
            $this->redis->setex($key, $this->ttl, 1);
            $this->setModificationTime();
        }
    }
}
