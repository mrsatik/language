<?php
declare(strict_types=1);

namespace mrsatik\Language\Cache;

use mrsatik\Language\Driver\DriverInterface;
use mrsatik\TCache\Item\KeyValue;
use mrsatik\TCache\Pool as CachePool;

class Pool implements PoolInterface
{
    private const CACHE_KEY = 'messages_%s__%s';

    /**
     * @var DriverInterface
     */
    private $driver;

    public function __construct(DriverInterface $driver)
    {
        $this->driver = $driver;
    }

    /**
     * {@inheritDoc}
     */
    public function getTranslations(string $module, string $locate): array
    {
        $key = $this->getCacheKey($module, $locate);
        $value = CachePool::getInstance()->getItem($key);
        if ($value === null) {
            $messages = $this->driver->getTranslations($module, $locate);
            $messagesToSave = [];
            foreach ($messages as $item) {
                $messagesToSave[$item['code']] = $item['value'];
            }
            $value = new KeyValue($key, $messagesToSave, [$module]);
            CachePool::getInstance()->save($value);
        }

        return $value->get();
    }

    /**
     * {@inheritDoc}
     */
    public function expireCache(string $module, string $locate): bool
    {
        return CachePool::getInstance()->deleteItem($this->getCacheKey($module, $locate));
    }

    /**
     * Ключ кеша
     * @param string $module
     * @param string $locate
     * @return string
     */
    private function getCacheKey(string $module, string $locate): string
    {
        return \sprintf(self::CACHE_KEY, $locate, $module);
    }
}
