<?php

declare(strict_types=1);

namespace Helis\SettingsManagerBundle\Provider\Traits;

use Predis\Client;
use Predis\Pipeline\Pipeline;
use Redis;

/** @property int $ttl */
/** @property Redis|Client|Pipeline $redis */
trait RedisModificationTrait
{
    /** @var string */
    private $modificationTimeKey = 'settings_modification_time';

    public function setModificationTimeKey(string $modificationTimeKey): void
    {
        $this->modificationTimeKey = $modificationTimeKey;
    }

    private function setModificationTime(): int
    {
        $time = (int) round(microtime(true) * 10000);
        $this->redis->setex($this->modificationTimeKey, $this->ttl, $time);

        return $time;
    }

    public function getModificationTime(): int
    {
        $time = (int) $this->redis->get($this->modificationTimeKey);

        if (empty($time)) {
            $time = $this->setModificationTime();
        }

        return $time;
    }
}
