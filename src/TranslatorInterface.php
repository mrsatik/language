<?php
declare(strict_types=1);

namespace mrsatik\Language;

use Symfony\Component\Translation\TranslatorInterface as OrigTranslatorInterface;

interface TranslatorInterface
{
    /**
     * Перевод сообщения
     *
     * @param string $id сообщение/ключ
     * @param array $params параметры
     * @param string $domain домен
     * @return string
     */
    public function t(string $id, ?array $params = [], ?string $domain = null): string;

    /**
     * Форматирование кастомного сообщения
     * @param $str
     * @param array $params
     * @return string
     */
    public function message(string $str, ?array $params = []): string;

    /**
     * Список сообщений
     * @param null|string $domain
     * @return array
     */
    public function getResources(?string $domain = null): array;

    /**
     * текущая локаль
     * @return string
     */
    public function getLocale(): string;

    /**
     * @return OrigTranslator
     */
    public function getTranslator(): OrigTranslatorInterface;

    /**
     * Текущая локаль дефолтного языка?
     * @return bool
     */
    public function isDefaultLang(): bool;
}
