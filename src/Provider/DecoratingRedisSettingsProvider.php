<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Provider\Traits\RedisModificationTrait;
use Helis\SettingsManagerBundle\Settings\Traits\DomainNameExtractTrait;
use Redis;
use Symfony\Component\Serializer\SerializerInterface;

class DecoratingRedisSettingsProvider implements ModificationAwareSettingsProviderInterface
{
    use DomainNameExtractTrait;
    use RedisModificationTrait;

    private const DOMAIN_KEY = 'domain';
    private const HASHMAP_KEY = 'hashmap';

    protected $decoratingProvider;
    protected $redis;
    protected $serializer;

    protected $ttl;
    protected $namespace;

    public function __construct(
        SettingsProviderInterface $decoratingProvider,
        Redis $redis,
        SerializerInterface $serializer
    ) {
        $this->decoratingProvider = $decoratingProvider;
        $this->redis = $redis;
        $this->serializer = $serializer;

        $this->ttl = 604800;
        $this->namespace = 'settings_provider_cache';
    }

    public function setTtl(int $ttl): void
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

        $pipe = $this->redis->multi(Redis::PIPELINE);
        foreach ($domainNames as $domainName) {
            $pipe->hGetAll($this->getNamespacedKey($domainName));
        }
        $result = $pipe->exec();

        $out = [];
        foreach ($result as $d => $domainGroup) {
            foreach ($domainGroup as $s => $item) {
                if ($item !== null && $item !== false) {
                    $out[] = $this->serializer->deserialize($item, SettingModel::class, 'json');
                }
            }
        }

        return array_values(array_filter($out));
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $this->buildHashmap();

        $pipe = $this->redis->multi(Redis::PIPELINE);
        foreach ($domainNames as $domainName) {
            $pipe->hMGet($this->getNamespacedKey($domainName), $settingNames);
        }
        $result = $pipe->exec();
        $out = [];

        foreach ($result as $d => $domainGroup) {
            foreach ($domainGroup as $s => $item) {
                if ($item !== false && $item !== null) {
                    $out[] = $this->serializer->deserialize($item, SettingModel::class, 'json');
                }
            }
        }

        return array_values(array_filter($out));
    }

    public function save(SettingModel $settingModel): bool
    {
        $output = $this->decoratingProvider->save($settingModel);

        // trigger domain warmup, in case of empty hashes
        $this->getDomains();
        $this->getDomains(true);

        $pipe = $this->redis->multi(Redis::PIPELINE);
        $domainName = $settingModel->getDomain()->getName();
        $serializedSetting = $this->serializer->serialize($settingModel, 'json');
        $serializedDomain = $this->serializer->serialize($settingModel->getDomain(), 'json');

        $pipe->hSetNx($this->getDomainKey(false), $domainName, $serializedDomain);
        if ($settingModel->getDomain()->isEnabled()) {
            $pipe->hSetNx($this->getDomainKey(true), $domainName, $serializedDomain);
        }

        $pipe->hSet(
            $this->getNamespacedKey($settingModel->getDomain()->getName()),
            $settingModel->getName(),
            $serializedSetting
        );
        $pipe->exec();

        $this->setModificationTime();

        return $output;
    }

    public function delete(SettingModel $settingModel): bool
    {
        $output = $this->decoratingProvider->delete($settingModel);

        $pipe = $this->redis->multi(Redis::PIPELINE);
        $pipe->hDel($this->getNamespacedKey($settingModel->getDomain()->getName()), $settingModel->getName());

        $domainName = $settingModel->getDomain()->getName();
        $domainNames = $this->extractDomainNames($this->decoratingProvider->getDomains());
        if (!in_array($domainName, $domainNames)) {
            $pipe->hDel($this->getDomainKey(true), $domainName);
            $pipe->hDel($this->getDomainKey(false), $domainName);
        }
        $pipe->exec();

        $this->setModificationTime();

        return $output;
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $key = $this->getDomainKey($onlyEnabled);
        $domains = $this->redis->hGetAll($key);

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
            $this->redis->hMSet($key, $dictionary);
            $this->setModificationTime();
        }

        return $domains;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $output = $this->decoratingProvider->updateDomain($domainModel);

        $pipe = $this->redis->multi(Redis::PIPELINE);
        $serializedDomain = $this->serializer->serialize($domainModel, 'json');
        $pipe->hSet($this->getDomainKey(false), $domainModel->getName(), $serializedDomain);
        if ($domainModel->isEnabled()) {
            $pipe->hSet($this->getDomainKey(true), $domainModel->getName(), $serializedDomain);
        } else {
            $pipe->hDel($this->getDomainKey(true), $domainModel->getName());
        }
        $pipe->exec();
        $this->setModificationTime();
        $this->buildHashmap(true, $domainModel->getName());

        return $output;
    }

    public function deleteDomain(string $domainName): bool
    {
        $output = $this->decoratingProvider->deleteDomain($domainName);

        $pipe = $this->redis->multi(Redis::PIPELINE);
        $pipe->del([$this->getNamespacedKey($domainName)]);
        $pipe->hDel($this->getDomainKey(true), $domainName);
        $pipe->hDel($this->getDomainKey(false), $domainName);
        $pipe->exec();
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
            $pipe = $this->redis->multi(Redis::PIPELINE);
            foreach ($settings as $setting) {
                $pipe->hSet(
                    $this->getNamespacedKey($setting->getDomain()->getName()),
                    $setting->getName(),
                    $this->serializer->serialize($setting, 'json')
                );
            }

            if ($isBuilt === false || (int)$isBuilt === 0) {
                $pipe->setex($key, $this->ttl, 1);
            }
            $pipe->exec();
            $this->setModificationTime();
        } else {
            if ($isBuilt === false || (int)$isBuilt === 0) {
                $this->redis->setex($key, $this->ttl, 1);
                $this->setModificationTime();
            }
        }
    }
}
