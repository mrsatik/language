<?php
declare(strict_types=1);

namespace mrsatik\Language\Driver;

interface DriverInterface
{
    /**
     * Возвращает список переводов модуля
     * @param string $module
     * @param string $lang
     * @return array
     */
    public function getTranslations(string $module, string $lang): array;
}