<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Settings\Traits\DomainNameExtractTrait;
use Predis\Client;
use Predis\Pipeline\Pipeline;
use Symfony\Component\Serializer\SerializerInterface;

class DecoratingPredisSettingsProvider implements SettingsProviderInterface
{
    use DomainNameExtractTrait;

    private const DOMAIN_KEY = 'domain';
    private const HASHMAP_KEY = 'hashmap';

    protected $decoratingProvider;
    protected $redis;
    protected $serializer;

    protected $ttl;
    protected $namespace;

    public function __construct(
        SettingsProviderInterface $decoratingProvider,
        Client $redis,
        SerializerInterface $serializer
    ) {
        $this->decoratingProvider = $decoratingProvider;
        $this->redis = $redis;
        $this->serializer = $serializer;

        $this->ttl = 604800;
        $this->namespace = 'settings_provider_cache';
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

        foreach ($result as $d => $domainGroup) {
            foreach ($domainGroup as $s => $item) {
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

        foreach ($result as $d => $domainGroup) {
            foreach ($domainGroup as $s => $item) {
                if ($item !== null) {
                    $out[] = $this->serializer->deserialize($item, SettingModel::class, 'json');
                }
            }
        }

        return array_values(array_filter($out));
    }

    public function save(SettingModel $settingModel): bool
    {
        $output = $this->decoratingProvider->save($settingModel);

        $newDomain = true;
        /** @var DomainModel $domain */
        foreach ($this->getDomains() as $domain) {
            if ($domain->getName() === $settingModel->getDomain()->getName()) {
                $newDomain = false;
                break;
            }
        }

        $serializedSetting = $this->serializer->serialize($settingModel, 'json');

        if ($newDomain) {
            $this->redis->pipeline(function (Pipeline $pipe) use ($settingModel, $serializedSetting) {
                $serializedDomain = $this->serializer->serialize($settingModel->getDomain(), 'json');

                $pipe->hset($this->getDomainKey(false), $settingModel->getDomain()->getName(), $serializedDomain);
                if ($settingModel->getDomain()->isEnabled()) {
                    $pipe->hset($this->getDomainKey(true), $settingModel->getDomain()->getName(), $serializedDomain);
                }

                $pipe->hset(
                    $this->getNamespacedKey($settingModel->getDomain()->getName()),
                    $settingModel->getName(),
                    $serializedSetting
                );
            });
        } else {
            $this->redis->hset(
                $this->getNamespacedKey($settingModel->getDomain()->getName()),
                $settingModel->getName(),
                $serializedSetting
            );
        }

        return $output;
    }

    public function delete(SettingModel $settingModel): bool
    {
        $output = $this->decoratingProvider->delete($settingModel);

        $this->redis->pipeline(function (Pipeline $pipe) use ($output, $settingModel) {
            $pipe->hdel($this->getNamespacedKey($settingModel->getDomain()->getName()), [$settingModel->getName()]);
            $domainName = $settingModel->getDomain()->getName();
            $domainNames = $this->extractDomainNames($this->decoratingProvider->getDomains());
            if (!in_array($domainName, $domainNames)) {
                $pipe->hdel($this->getDomainKey(true), [$domainName]);
                $pipe->hdel($this->getDomainKey(false), [$domainName]);
            }
        });

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

        if (count($domains) > 0) {
            $dictionary = [];
            foreach ($domains as $domain) {
                $dictionary[$domain->getName()] = $this->serializer->serialize($domain, 'json');
            }
            $this->redis->hmset($key, $dictionary);
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

        return $output;
    }

    private function getDomainKey(bool $onlyEnabled = false): string
    {
        return $this->getNamespacedKey(self::DOMAIN_KEY . ($onlyEnabled ? '_oe' : ''));
    }

    private function getHashMapKey(): string
    {
        return $this->getNamespacedKey(self::HASHMAP_KEY);
    }

    private function getNamespacedKey(string $key): string
    {
        return sprintf('%s[%s]', $this->namespace, $key);
    }

    protected function buildHashmap(bool $force = false, ?string $domainName = null): void
    {
        $key = $this->getHashMapKey();
        $isBuilt = $this->redis->get($key);
        if ((int)$isBuilt === 1 && $force === false) {
            return;
        }

        if ($domainName !== null) {
            $domains = [$domainName];
        } else {
            $domains = $this->extractDomainNames($this->decoratingProvider->getDomains());
        }

        $settings = $this->decoratingProvider->getSettings($domains);

        if (!empty($settings)) {
            $this->redis->pipeline(function (Pipeline $pipe) use ($settings, $key, $isBuilt) {
                foreach ($settings as $setting) {
                    $pipe->hset(
                        $this->getNamespacedKey($setting->getDomain()->getName()),
                        $setting->getName(),
                        $this->serializer->serialize($setting, 'json')
                    );
                }

                if ($isBuilt === null || (int)$isBuilt === 0) {
                    $pipe->setex($key, $this->ttl, 1);
                }
            });
        } else {
            if ($isBuilt === null || (int)$isBuilt === 0) {
                $this->redis->setex($key, $this->ttl, 1);
            }
        }
    }
}
