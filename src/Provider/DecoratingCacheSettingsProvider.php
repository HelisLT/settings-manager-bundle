<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Helis\SettingsManagerBundle\Settings\Traits\DomainNameExtractTrait;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Serializer\SerializerInterface;

class DecoratingCacheSettingsProvider implements ModificationAwareSettingsProviderInterface
{
    use DomainNameExtractTrait;

    /** @var SettingsProviderInterface */
    private $decoratingProvider;

    /** @var SerializerInterface */
    protected $serializer;

    /** @var AdapterInterface */
    private $cache;

    /** @var Factory */
    private $lockFactory;

    /** @var int */
    private $checkValidityInterval;

    /** @var string */
    private $modificationTimeKey = 'settings_modification_time';

    public function __construct(
        ModificationAwareSettingsProviderInterface $decoratingProvider,
        SerializerInterface $serializer,
        AdapterInterface $cache,
        Factory $lockFactory,
        int $checkValidityInterval = 30
    ) {
        $this->decoratingProvider = $decoratingProvider;
        $this->serializer = $serializer;
        $this->lockFactory = $lockFactory;
        $this->cache = $cache;
        $this->checkValidityInterval = $checkValidityInterval;
    }

    /**
     * {@inheritDoc}
     */
    public function getSettings(array $domainNames): array
    {
        $this->clearAndWarmupIfNeeded();
        $settings = [];

        foreach ($domainNames as $domainName) {
            foreach ($this->getSettingNames($domainName) as $settingName) {
                $key = $this->getSettingKey($domainName, $settingName);
                $item = $this->getCached($key);
                if ($item->isHit()) {
                    $settings[] = $this->serializer->deserialize($item->get(), SettingModel::class, 'json');
                }
            }
        }
        $this->cache->commit();

        return $settings;
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $this->clearAndWarmupIfNeeded();
        $settings = [];

        foreach ($domainNames as $domainName) {
            foreach ($settingNames as $settingName) {
                $key = $this->getSettingKey($domainName, $settingName);
                $item = $this->getCached($key);
                if ($item->isHit()) {
                    $settings[] = $this->serializer->deserialize($item->get(), SettingModel::class, 'json');
                }
            }
        }

        return $settings;
    }

    /**
     * @return string[]
     */
    private function getSettingNames(string $domainName): array
    {
        return $this->getCached($this->getSettingNamesKey($domainName))->get() ?? [];
    }

    /**
     * {@inheritDoc}
     */
    public function getDomains(bool $onlyEnabled = false): array
    {
        $this->clearAndWarmupIfNeeded();
        $key = $this->getDomainKey($onlyEnabled);
        $cacheItem = $this->getCached($key);

        if ($cacheItem->isHit()) {
            return $this->deserializeArray($cacheItem->get());
        }

        $domains = $this->decoratingProvider->getDomains($onlyEnabled);

        if (!empty($domains)) {
            $this->storeCached($cacheItem, $this->serializeArray($domains));
        }

        return $domains;
    }

    private function serializeArray(array $elements): array
    {
        foreach ($elements as &$element) {
            $element = $this->serializer->serialize($element, 'json');
        }

        return $elements;
    }

    private function deserializeArray(array $elements): array
    {
        foreach ($elements as &$element) {
            $element = $this->serializer->deserialize($element, DomainModel::class, 'json');
        }

        return $elements;
    }

    public function isReadOnly(): bool
    {
        return $this->decoratingProvider->isReadOnly();
    }

    public function save(SettingModel $settingModel): bool
    {
        return $this->decoratingProvider->save($settingModel);
    }

    public function delete(SettingModel $settingModel): bool
    {
        return $this->decoratingProvider->delete($settingModel);
    }

    public function updateDomain(DomainModel $domainModel): bool
    {
        return $this->decoratingProvider->updateDomain($domainModel);
    }

    public function deleteDomain(string $domainName): bool
    {
        return $this->decoratingProvider->deleteDomain($domainName);
    }

    private function getCached(string $key): CacheItem
    {
        return $this->cache->getItem($key);
    }

    private function storeCached(CacheItem $cacheItem, $value, bool $commit = true): void
    {
        $cacheItem->set($value);
        $this->cache->saveDeferred($cacheItem);

        if (true === $commit) {
            $this->cache->commit();
        }
    }

    private function getSettingNamesKey(string $domainName): string
    {
        return sprintf('setting_names[%s]', $domainName);
    }

    private function getSettingKey(string $domainName, string $settingName): string
    {
        return sprintf('setting[%s][%s]', $domainName, $settingName);
    }

    private function getDomainKey(bool $onlyEnabled = false): string
    {
        return sprintf('domain%s', $onlyEnabled ? '_oe' : '');
    }

    private function clearAndWarmupIfNeeded(): void
    {
        /** @var CacheItem $lastCheck */
        $lastCheck = $this->cache->getItem('last_modification_time_check');
        $time = time();
        if ($lastCheck->isHit() && time() - $lastCheck->get() < $this->checkValidityInterval) {
            return;
        }

        if ($this->getModificationTime() < $this->decoratingProvider->getModificationTime()) {
            $lock = $this->lockFactory->createLock(__FUNCTION__);

            if (!$lock->acquire()) {
                usleep(50000);
                $this->clearAndWarmupIfNeeded();
            }

            try {
                $this->cache->clear();
                $this->setModificationTime();
                $this->warmup();
            } finally {
                $lock->release();
            }
        }

        $lastCheck->set($time);
        $this->cache->save($lastCheck);
    }

    private function warmup(): void
    {
        foreach ($this->extractDomainNames($this->getDomains()) as $domainName) {
            $key = $this->getSettingNamesKey($domainName);
            $cacheItem = $this->getCached($key);
            $settingNames = [];

            foreach ($this->decoratingProvider->getSettings([$domainName]) as $setting) {
                $settingNames[] = $setting->getName();
                $settingKey = $this->getSettingKey($domainName, $setting->getName());
                $serializedSetting = $this->serializer->serialize($setting, 'json');
                $this->storeCached($this->getCached($settingKey), $serializedSetting, false);
            }

            $this->storeCached($cacheItem, $settingNames, false);
        }
    }

    public function setModificationTimeKey(string $modificationTimeKey): void
    {
        $this->modificationTimeKey = $modificationTimeKey;
    }

    private function setModificationTime(bool $commit = false): int
    {
        $time = (int)round(microtime(true) * 10000);
        $cachedValue = $this->cache->getItem($this->modificationTimeKey);
        $cachedValue->set($time);
        $this->cache->saveDeferred($cachedValue);

        if ($commit) {
            $this->cache->commit();
        }

        return $time;
    }

    public function getModificationTime(): int
    {
        $cachedValue = $this->cache->getItem($this->modificationTimeKey);
        if ($cachedValue->isHit()) {
            return $cachedValue->get();
        }

        return 0;
    }
}
