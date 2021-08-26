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

    private const LOCK_RETRY_INTERVAL_MS = 50000; // microseconds

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
        $this->clearIfNeeded();
        $this->warmupByDomainNames($domainNames);
        $settings = [];

        foreach ($domainNames as $domainName) {
            $settingNames = $this->getSettingNamesByDomain($domainName);
            $settings = array_merge($settings, $this->collectCachedSettings($domainName, $settingNames));
        }
        $this->cache->commit();

        return $settings;
    }

    /**
     * {@inheritDoc}
     */
    public function getSettingsByName(array $domainNames, array $settingNames): array
    {
        $this->clearIfNeeded();
        $this->warmupBySettingNames($domainNames, $settingNames);
        $settings = [];

        foreach ($domainNames as $domainName) {
            $settings = array_merge($settings, $this->collectCachedSettings($domainName, $settingNames));
        }

        return $settings;
    }

    public function getSettingsByTag(array $domainNames, string $tagName): array
    {
        $this->clearIfNeeded();
        $this->warmupByTag($domainNames, $tagName);
        $settings = [];

        foreach ($domainNames as $domainName) {
            $settingNames = $this->getSettingNamesByTag($domainName, $tagName);
            $settings = array_merge($settings, $this->collectCachedSettings($domainName, $settingNames));
        }

        return $settings;
    }

    /**
     * {@inheritDoc}
     */
    public function getDomains(bool $onlyEnabled = false): array
    {
        $this->clearIfNeeded();
        $key = $this->getDomainKey($onlyEnabled);
        $cacheItem = $this->getCached($key);

        if ($cacheItem->isHit()) {
            return $this->deserializeArray(array_filter($cacheItem->get()));
        }

        $domains = $this->decoratingProvider->getDomains($onlyEnabled);

        if (!empty($domains)) {
            $this->storeCached($cacheItem, $this->serializeArray($domains));
        }

        return $domains;
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

    public function setModificationTimeKey(string $modificationTimeKey): void
    {
        $this->modificationTimeKey = $modificationTimeKey;
    }

    public function getModificationTime(): int
    {
        $cachedValue = $this->cache->getItem($this->modificationTimeKey);
        if ($cachedValue->isHit()) {
            return $cachedValue->get();
        }

        return 0;
    }

    private function clearIfNeeded(): void
    {
        $lastCheck = $this->cache->getItem('last_modification_time_check');
        $time = time();
        if ($lastCheck->isHit() && time() - $lastCheck->get() < $this->checkValidityInterval) {
            return;
        }

        if ($this->getModificationTime() < $this->decoratingProvider->getModificationTime()) {
            $lock = $this->lockFactory->createLock(__FUNCTION__);

            if (!$lock->acquire()) {
                usleep(self::LOCK_RETRY_INTERVAL_MS);
                $this->clearIfNeeded();

                return;
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

    private function warmupByDomainNames(array $domainNames): void
    {
        $missingDomainNames = [];

        foreach ($domainNames as $domainName) {
            if ($this->isDomainSettingsWarm($domainName)) {
                continue;
            }

            $missingDomainNames[] = $domainName;
        }

        if (count($missingDomainNames) > 0) {
            $lock = $this->lockFactory->createLock(__FUNCTION__);
            if (!$lock->acquire()) {
                usleep(self::LOCK_RETRY_INTERVAL_MS);
                $this->warmupByDomainNames($domainNames);

                return;
            }

            try {
                $this->warmupDomainSettings($missingDomainNames);
            } finally {
                $lock->release();
            }
        }

        $this->cache->commit();
    }

    private function warmupBySettingNames(array $domainNames, array $settingNames): void
    {
        $missingDomainNames = [];
        $missingSettingNames = [];

        foreach ($domainNames as $domainName) {
            if ($this->isDomainSettingsWarm($domainName)) {
                continue;
            }

            foreach ($settingNames as $settingName) {
                if ($this->isSettingWarm($domainName, $settingName)) {
                    continue;
                }

                $missingDomainNames[$domainName] = $domainName;
                $missingSettingNames[$settingName] = $settingName;
            }
        }

        if (count($missingSettingNames) > 0) {
            $missingDomainNames = array_values($missingDomainNames);
            $missingSettingNames = array_values($missingSettingNames);

            $lock = $this->lockFactory->createLock(__FUNCTION__);
            if (!$lock->acquire()) {
                usleep(self::LOCK_RETRY_INTERVAL_MS);
                $this->warmupBySettingNames($domainNames, $settingNames);

                return;
            }

            try {
                $this->warmupParticularSettings($missingDomainNames, $missingSettingNames);
            } finally {
                $lock->release();
            }
        }

        $this->cache->commit();
    }

    private function warmupByTag(array $domainNames, string $tagName): void
    {
        $missingDomainNames = [];

        foreach ($domainNames as $domainName) {
            if ($this->isDomainSettingsWarm($domainName) || $this->isTagWarm($domainName, $tagName)) {
                continue;
            }

            $missingDomainNames[] = $domainName;
        }

        if (count($missingDomainNames) > 0) {
            $lock = $this->lockFactory->createLock(__FUNCTION__);
            if (!$lock->acquire()) {
                usleep(self::LOCK_RETRY_INTERVAL_MS);
                $this->warmupByTag($domainNames, $tagName);

                return;
            }

            try {
                $this->warmupTaggedSettings($missingDomainNames, $tagName);
            } finally {
                $lock->release();
            }
        }

        $this->cache->commit();
    }

    /**
     * Warmup domain settings.
     *
     * As this method fetches all settings from given domains, it can build all three kinds of cache items:
     *  - settings
     *  - setting names by domain
     *  - setting names by tag
     *
     * @param array $domainNames
     */
    private function warmupDomainSettings(array $domainNames): void
    {
        $mappedSettingNames = [];
        $mappedTaggedSettingNames = [];

        // build settings cache items
        foreach ($this->decoratingProvider->getSettings($domainNames) as $setting) {
            $domainName = $setting->getDomain()->getName();
            $mappedSettingNames[$domainName][] = $setting->getName();

            $settingKey = $this->getSettingKey($domainName, $setting->getName());
            $serializedSetting = $this->serializer->serialize($setting, 'json');
            $this->storeCached($this->getCached($settingKey), $serializedSetting, false);

            foreach ($setting->getTags() as $tag) {
                if (!isset($mappedTaggedSettingNames[$domainName])) {
                    $mappedTaggedSettingNames[$domainName] = [];
                }

                $mappedTaggedSettingNames[$domainName][$tag->getName()][] = $setting->getName();
            }
        }

        // build setting names by domain cache items
        foreach ($mappedSettingNames as $domainName => $settingNames) {
            $key = $this->getSettingNamesKey($domainName);
            $cacheItem = $this->getCached($key);

            $this->storeCached($cacheItem, $settingNames, false);
        }

        // build setting names by tag cache items
        foreach ($mappedTaggedSettingNames as $domainName => $taggedSettingNames) {
            foreach ($taggedSettingNames as $tagName => $settingNames) {
                $key = $this->getTaggedSettingNamesKey($domainName, $tagName);
                $cacheItem = $this->getCached($key);

                $this->storeCached($cacheItem, $settingNames, false);
            }
        }
    }

    /**
     * Warmup particular settings.
     *
     * As this method fetches only some particular settings from given domains,
     * it can build only settings cache items.
     *
     * @param array $domainNames
     * @param array $settingNames
     */
    private function warmupParticularSettings(array $domainNames, array $settingNames): void
    {
        $settings = $this->decoratingProvider->getSettingsByName($domainNames, $settingNames);
        $indexedSettings = [];
        foreach ($settings as $setting) {
            if (!isset($indexedSettings[$setting->getDomain()->getName()])) {
                $indexedSettings[$setting->getDomain()->getName()] = [];
            }

            $indexedSettings[$setting->getDomain()->getName()][$setting->getName()] = $setting;
        }

        // create cache item for each requested domainName, settingName pair
        foreach ($domainNames as $domainName) {
            foreach ($settingNames as $settingName) {
                $settingKey = $this->getSettingKey($domainName, $settingName);
                $loadedSetting = $indexedSettings[$domainName][$settingName] ?? null;

                if (!$loadedSetting instanceof SettingModel) {
                    $this->storeCached($this->getCached($settingKey), null, false);

                    continue;
                }

                $serializedSetting = $this->serializer->serialize($loadedSetting, 'json');
                $this->storeCached($this->getCached($settingKey), $serializedSetting, false);
            }
        }
    }

    /**
     * Warmup tagged settings.
     *
     * As this method fetches only tagged settings from given domains, it can build only these cache items:
     *  - settings
     *  - setting names by tag
     *
     * @param array  $domainNames
     * @param string $tagName
     */
    private function warmupTaggedSettings(array $domainNames, string $tagName): void
    {
        $mappedSettingNames = [];

        $settings = $this->decoratingProvider->getSettingsByTag($domainNames, $tagName);

        // cache settings cache items
        foreach ($settings as $setting) {
            $mappedSettingNames[$setting->getDomain()->getName()][] = $setting->getName();

            $settingKey = $this->getSettingKey($setting->getDomain()->getName(), $setting->getName());
            $serializedSetting = $this->serializer->serialize($setting, 'json');
            $this->storeCached($this->getCached($settingKey), $serializedSetting, false);
        }

        // build setting names by tag cache items
        foreach ($mappedSettingNames as $domainName => $settingNames) {
            $key = $this->getTaggedSettingNamesKey($domainName, $tagName);
            $cacheItem = $this->getCached($key);

            $this->storeCached($cacheItem, $settingNames, false);
        }
    }

    private function isDomainSettingsWarm(string $domainName): bool
    {
        return $this->getCached($this->getSettingNamesKey($domainName))->isHit();
    }

    private function isSettingWarm(string $domainName, string $settingName): bool
    {
        return $this->getCached($this->getSettingKey($domainName, $settingName))->isHit();
    }

    private function isTagWarm(string $domainName, string $tagName): bool
    {
        return $this->getCached($this->getTaggedSettingNamesKey($domainName, $tagName))->isHit();
    }

    /**
     * @param string $domainName
     *
     * @return string[]
     */
    private function getSettingNamesByDomain(string $domainName): array
    {
        return $this->getCached($this->getSettingNamesKey($domainName))->get() ?? [];
    }

    /**
     * @param string $domainName
     * @param string $tagName
     *
     * @return string[]
     */
    private function getSettingNamesByTag(string $domainName, string $tagName): array
    {
        return $this->getCached($this->getTaggedSettingNamesKey($domainName, $tagName))->get() ?? [];
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

    private function getTaggedSettingNamesKey(string $domainName, string $tagName): string
    {
        return sprintf('tagged_setting_names[%s][%s]', $domainName, $tagName);
    }

    private function getDomainKey(bool $onlyEnabled = false): string
    {
        return sprintf('domain%s', $onlyEnabled ? '_oe' : '');
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

    private function collectCachedSettings(string $domainName, array $settingNames): array
    {
        $settings = [];

        foreach ($settingNames as $settingName) {
            $key = $this->getSettingKey($domainName, $settingName);
            $item = $this->getCached($key);
            if ($item->isHit() && null !== $item->get()) {
                $settings[] = $this->serializer->deserialize($item->get(), SettingModel::class, 'json');
            }
        }

        return $settings;
    }
}
