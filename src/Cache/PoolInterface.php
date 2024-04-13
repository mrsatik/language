<?php
declare(strict_types=1);

namespace mrsatik\Language\Cache;

use mrsatik\Language\Driver\DriverInterface;

interface PoolInterface extends DriverInterface
{
    /**
     * Протухание ключа кеша
     * @param string $module код модуля
     * @param string $locate локаль
     * @return bool
     */
    public function expireCache(string $module, string $locate): bool;
}