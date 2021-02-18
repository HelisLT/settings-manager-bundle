<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Serializer\SerializerInterface;

class DecoratingFilesystemSettingsProvider implements ModificationAwareSettingsProviderInterface
{
    public const MODIFICATION_TIME_KEY = 'settings_modification_time';

    /** @var SettingsProviderInterface */
    private $decoratingProvider;

    /** @var SerializerInterface */
    protected $serializer;

    /** @var PhpFilesAdapter */
    private $cache;

    /** @var Factory */
    private $lockFactory;

    /** @var int */
    private $checkValidityInterval;

    public function __construct(
        ModificationAwareSettingsProviderInterface $decoratingProvider,
        SerializerInterface $serializer,
        string $nameSpace = 'settings_cache',
        int $checkValidityInterval = 30,
        bool $useOPcache = true
    ) {
        $this->decoratingProvider = $decoratingProvider;
        $this->serializer = $serializer;
        $this->checkValidityInterval = $checkValidityInterval;

        $this->lockFactory = new Factory(new FlockStore());
        $this->cache = $useOPcache ? new PhpFilesAdapter($nameSpace) : new FilesystemAdapter($nameSpace);
    }

    /**
     * {@inheritDoc}
     */
    public function getSettings(array $domainNames): array
    {
        $this->clearCacheIfNeeded();
        $settings = [];

        foreach ($domainNames as $domainName) {
            foreach ($this->getSettingNames($domainName) as $settingName) {
                $key = $this->getSettingKey($domainName, $settingName);
                $item = $this->getCached($key);
                $settings[] = $this->serializer->deserialize($item->get(), SettingModel::class, 'json');
            }
        }
        $this->cache->commit();

        return $settings;
    }

    /**
     * @param string $domainName
     * @return string[]
     */
    private function getSettingNames(string $domainName): array
    {
        $key = $this->getSettingNamesKey($domainName);
        $cacheItem = $this->getCached($key);

        if (!$cacheItem->isHit()) {
            $settingNames = [];
            foreach ($this->decoratingProvider->getSettings([$domainName]) as $setting) {
                $settingNames[] = $setting->getName();
                $settingKey = $this->getSettingKey($domainName, $setting->getName());
                $serializedSetting = $this->serializer->serialize($setting, 'json');
                $this->storeCached($this->getCached($settingKey), $serializedSetting, false);
            }
            $this->storeCached($cacheItem, $settingNames, false);
        }

        return $cacheItem->get();
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $settings = [];

        foreach ($this->getSettings($domainNames) as $setting) {
            if (in_array($setting->getName(), $settingNames, true)) {
                $settings[] = $setting;
            }
        }

        return $settings;
    }

    /**
     * {@inheritDoc}
     */
    public function getDomains(bool $onlyEnabled = false): array
    {
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

    private function clearCacheIfNeeded(): void
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
                $this->clearCacheIfNeeded();
            }

            try {
                $this->cache->clear();
                $this->setModificationTime();
            } finally {
                $lock->release();
            }
        }

        $lastCheck->set($time);
        $this->cache->save($lastCheck);
    }

    private function setModificationTime(bool $commit = false): int
    {
        $time = (int)round(microtime(true) * 10000);
        /** @var CacheItem $cachedValue */
        $cachedValue = $this->cache->getItem(self::MODIFICATION_TIME_KEY);
        $cachedValue->set($time);
        $this->cache->saveDeferred($cachedValue);

        if ($commit) {
            $this->cache->commit();
        }

        return $time;
    }

    public function getModificationTime(): int
    {
        /** @var CacheItem $cachedValue */
        $cachedValue = $this->cache->getItem(self::MODIFICATION_TIME_KEY);
        if ($cachedValue->isHit()) {
            return $cachedValue->get();
        }

        return $this->setModificationTime();
    }
}
