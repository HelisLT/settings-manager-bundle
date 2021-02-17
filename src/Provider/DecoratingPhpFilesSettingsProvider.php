<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider;

use Helis\SettingsManagerBundle\Model\DomainModel;
use Helis\SettingsManagerBundle\Model\SettingModel;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\Lock\Factory;
use Symfony\Component\Lock\Store\FlockStore;

class DecoratingPhpFilesSettingsProvider implements ModificationAwareSettingsProviderInterface
{
    public const MODIFICATION_TIME_KEY = 'settings_modification_time';

    /** @var SettingsProviderInterface */
    private $decoratingProvider;

    /** @var PhpFilesAdapter */
    private $cache;

    /** @var Factory */
    private $lockFactory;

    /** @var int */
    private $checkValidityInterval;

    public function __construct(
        ModificationAwareSettingsProviderInterface $decoratingProvider,
        int $checkValidityInterval = 30
    ) {
        $this->decoratingProvider = $decoratingProvider;
        $this->checkValidityInterval = $checkValidityInterval;

        $this->lockFactory = new Factory(new FlockStore());
        $this->cache = new PhpFilesAdapter('settings_cache');
    }

    public function getSettings(array $domainNames): array
    {
        return $this->getCached(__FUNCTION__, function ($domainNames) {
            return $this->decoratingProvider->getSettings($domainNames);
        }, $domainNames);
    }

    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        return $this->getCached(__FUNCTION__, function ($domainNames, $settingNames) {
            return $this->decoratingProvider->getSettingsByName($domainNames, $settingNames);
        }, $domainNames, $settingNames);
    }

    public function getDomains(bool $onlyEnabled = false): array
    {
        return $this->getCached(__FUNCTION__, function ($onlyEnabled) {
            return $this->decoratingProvider->getDomains($onlyEnabled);
        }, $onlyEnabled);
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

    private function getCached(string $operationName, callable $callable, ...$args)
    {
        $this->clearCacheIfNeeded();

        $operationKey = $this->getCacheKey($operationName, $args);
        $cachedValue = $this->cache->getItem($operationKey);

        if (!$cachedValue->isHit()) {
            $lock = $this->lockFactory->createLock($operationKey);
            if (!$lock->acquire()) {
                usleep(50000);

                return $this->getCached($operationName, $callable, ...$args);
            }
            try {
                $cachedValue->set($callable(...$args));
                $this->cache->save($cachedValue);
            } finally {
                $lock->release();
            }
        }

        return $cachedValue->get();
    }

    private function getCacheKey(string $operationName, array $args = []): string
    {
        return preg_replace('/[{}()\/\\\@:]/', '', $operationName . md5(serialize($args)));
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

    private function setModificationTime(): int
    {
        $time = (int) round(microtime(true) * 10000);
        /** @var CacheItem $cachedValue */
        $cachedValue = $this->cache->getItem(self::MODIFICATION_TIME_KEY);
        $cachedValue->set($time);
        $this->cache->save($cachedValue);

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
