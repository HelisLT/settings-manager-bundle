<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Settings\Traits\DomainNameExtractTrait;
use Redis;
use Symfony\Component\Serializer\SerializerInterface;

class DecoratingRedisSettingsProvider implements SettingsProviderInterface
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
            $pipe->hgetall($this->getNamespacedKey($domainName));
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
            $pipe->hmget($this->getNamespacedKey($domainName), $settingNames);
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
            $pipe = $this->redis->multi(Redis::PIPELINE);
            $pipe->del([$this->getDomainKey(false), $this->getDomainKey(true)]);
            $pipe->hset(
                $this->getNamespacedKey($settingModel->getDomain()->getName()),
                $settingModel->getName(),
                $serializedSetting
            );
            $pipe->exec();
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

        if ($output) {
            $pipe = $this->redis->multi(Redis::PIPELINE);
            $pipe->hdel(
                $this->getNamespacedKey($settingModel->getDomain()->getName()),
                $settingModel->getName()
            );
            $pipe->del([$this->getDomainKey(false), $this->getDomainKey(true)]);
            $pipe->exec();
        } else {
            $this->redis->del([$this->getDomainKey(false), $this->getDomainKey(true)]);
        }

        return $output;
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        $key = $this->getDomainKey($onlyEnabled);
        $domains = $this->redis->get($key);

        if ($domains) {
            return $this->serializer->deserialize($domains, DomainModel::class . '[]', 'json');
        }

        $domains = $this->decoratingProvider->getDomains($onlyEnabled);

        $this->redis->setex(
            $key,
            $this->ttl,
            $this->serializer->serialize($domains, 'json')
        );

        return $domains;
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        $output = $this->decoratingProvider->updateDomain($domainModel);

        $this->redis->del([$this->getDomainKey(false), $this->getDomainKey(true)]);
        $this->buildHashmap(true, $domainModel->getName());

        return $output;
    }

    public function deleteDomain(string $domainName): bool
    {
        $output = $this->decoratingProvider->deleteDomain($domainName);

        $this->redis->del([
            $this->getNamespacedKey($domainName),
            $this->getDomainKey(false),
            $this->getDomainKey(true),
        ]);

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

    private function buildHashmap(bool $force = false, ?string $domainName = null)
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
        } else {
            if ($isBuilt === false || (int)$isBuilt === 0) {
                $this->redis->setex($key, $this->ttl, 1);
            }
        }
    }
}
