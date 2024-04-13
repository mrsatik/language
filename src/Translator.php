<?php
declare(strict_types=1);

namespace mrsatik\Language;

use Exception;
use mrsatik\Language\Driver\Db;
use Symfony\Component\Translation\Translator as OrigTranslator;
use Symfony\Component\Translation\TranslatorInterface as OrigTranslatorInterface;
use Symfony\Component\Translation\Loader\ArrayLoader;
use MessageFormatter;
use PDO;
use mrsatik\Settings\Settings;
use mrsatik\Language\Cache\Pool;
use mrsatik\Language\Cache\PoolInterface;

/**
 * Класс транслятора
 *
 * - поддержка расширения php-intl
 * - поддержка шаблонов intl
 *
 */
class Translator implements TranslatorInterface, OrigTranslatorInterface
{
    /**
     * @var OrigTranslatorInterface транслятор
     */
    private $translator;

    private $translations = [];

    private $driver;

    private $moduleLoaded = [];

    /**
     * @var PoolInterface
     */
    private $cacheProvider;

    private $locale;

    /**
     * Конструктор транслятора
     *
     * @param string $lang язык
     * @param PDO $source источник выборки
     */
    public function __construct(string $lang, PDO $source)
    {
        $locales = Settings::getInstance()->getValue('common.lang.locale');
        if (isset($locales[$lang]) === false) {
            $this->locale = $locales[Settings::getInstance()->getValue('common.lang.default')];
        } else {
            $this->locale = $locales[$lang];
        }

        $this->translator = new OrigTranslator($this->locale);
        $this->translator->addLoader('array', new ArrayLoader());

        $this->driver = new Db($source);
    }

    /**
     * {@inheritdoc}
     */
    public function t(string $id, ?array $params = [], ?string $domain = null): string
    {
        if ($domain === null) {
            $domain = 'messages';
        }
        $location = $this->getLocale();
        if (\array_key_exists($domain, $this->moduleLoaded) === false) {
            $messages = $this->getTranslationMessages($domain);
            $this->translator->addResource('array', $messages, $location, $domain);
            $this->moduleLoaded[$domain] = true;
        }

        $paramsHash = md5(serialize($params));
        if (isset($this->translations[$location][$id][$paramsHash][$domain]) === false) {
            $translation = $this->translator->trans($id, [], $domain);
            $this->translations[$location][$id][$paramsHash][$domain] = $this->message($translation, $params);
        }
        return $this->translations[$location][$id][$paramsHash][$domain];
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $str, ?array $params = []): string
    {
        return $this->format($str, $params, $this->getLocale());
    }

    /**
     * {@inheritdoc}
     */
    public function getResources(?string $domain = null): array
    {
        if ($domain === null) {
            $domain = 'messages';
        }

        return $this->getTranslationMessages($domain);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * {@inheritdoc}
     */
    public function getTranslator(): OrigTranslatorInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return $this->t($id, $parameters, $domain);
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale) { }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        if ($domain === null) {
            $domain = 'messages';
        }
        $location = $this->getLocale();
        if (\array_key_exists($domain, $this->moduleLoaded) === false) {
            $messages = $this->getTranslationMessages($domain);
            $this->translator->addResource('array', $messages, $location, $domain);
            $this->moduleLoaded[$domain] = true;
        }

        $paramsHash = md5(serialize([$parameters, $number]));
        if (isset($this->translations[$location][$id][$paramsHash][$domain]) === false) {
            $translation = $this->translator->transChoice($id, $number, $parameters, $domain, $location);
            $this->translations[$location][$id][$paramsHash][$domain] = $this->message($translation, $parameters);
        }
        return $this->translations[$location][$id][$paramsHash][$domain];
    }

    /**
     * {@inheritdoc}
     */
    public function isDefaultLang(): bool
    {
        $locale = Settings::getInstance()->getValue('common.lang.locale');
        return $this->getLocale() === $locale[Settings::getInstance()->getValue('common.lang.default')];
    }

    /**
     * Форматирование сообщения
     *
     * @param string $pattern шаблон
     * @param array $params параметры
     * @param string $language локаль
     * @return string
     * @throws Exception
     */
    private function format(string $pattern, array $params, string $language): string
    {
        if ($params === []) {
            return $pattern;
        }

        if (
            class_exists('MessageFormatter', false) === true
            && preg_match('~{\s*[\d\w]+\s*,~u', $pattern)
        ) {
            $newParams = [];
            $pattern = $this->replaceNamedArguments($pattern, $params, $newParams);
            $params = $newParams;

            $formatter = new MessageFormatter($language, $pattern);

            if ($formatter === null) {
                throw new Exception('Message pattern is invalid: ' . intl_get_error_message());
            }

            $result = $formatter->format($params);

            if ($result === false) {
                return $pattern;
            } else {
                return $result;
            }
        }

        // замена, если не поддерживается расширение php-intl
        $p = [];
        foreach ($params as $name => $value) {
            $p['{' . $name . '}'] = $value;
        }

        return strtr($pattern, $p);
    }

    /**
     * Замена именнованных ключей числовыми с экранированием кавычками
     *
     * @param string $pattern шаблон
     * @param array $givenParams параметры
     * @param array $resultingParams результрующие параметры
     * @param array $map соответствии
     * @return string
     */
    private function replaceNamedArguments($pattern, $givenParams, &$resultingParams = [], &$map = [])
    {
        if (($tokens = $this->tokenizePattern($pattern)) === false) {
            return false;
        }
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }
            $param = trim($token[0]);
            if (isset($givenParams[$param])) {
                if (!isset($map[$param])) {
                    $map[$param] = count($map);
                    $resultingParams[$map[$param]] = $givenParams[$param];
                }
                $token[0] = $map[$param];
                $quote = '';
            } else {
                $quote = "'";
            }
            $type = isset($token[1]) ? trim($token[1]) : 'none';
            if ($type === 'plural' || $type === 'select') {
                if (!isset($token[2])) {
                    return false;
                }
                if (($subtokens = $this->tokenizePattern($token[2])) === false) {
                    return false;
                }
                $c = count($subtokens);
                for ($k = 0; $k + 1 < $c; $k++) {
                    if (is_array($subtokens[$k]) || !is_array($subtokens[++$k])) {
                        return false;
                    }
                    $subpattern = $this->replaceNamedArguments(implode(',', $subtokens[$k]), $givenParams, $resultingParams, $map);
                    $subtokens[$k] = $quote . '{' . $quote . $subpattern . $quote . '}' . $quote;
                }
                $token[2] = implode('', $subtokens);
            }
            $tokens[$i] = $quote . '{' . $quote . implode(',', $token) . $quote . '}' . $quote;
        }
        return implode('', $tokens);
    }

    /**
     * Токенизация шаблона
     *
     * @param string $pattern шаблон
     * @return array|bool
     */
    private function tokenizePattern($pattern)
    {
        $depth = 1;
        if (($start = $pos = mb_strpos($pattern, '{', 0)) === false) {
            return [$pattern];
        }
        $tokens = [mb_substr($pattern, 0, $pos)];
        while (true) {
            $open = mb_strpos($pattern, '{', $pos + 1);
            $close = mb_strpos($pattern, '}', $pos + 1);
            if ($open === false && $close === false) {
                break;
            }
            if ($open === false) {
                $open = mb_strlen($pattern);
            }
            if ($close > $open) {
                $depth++;
                $pos = $open;
            } else {
                $depth--;
                $pos = $close;
            }
            if ($depth === 0) {
                $tokens[] = explode(',', mb_substr($pattern, $start + 1, $pos - $start - 1), 3);
                $start = $pos + 1;
                $tokens[] = mb_substr($pattern, $start, $open - $start);
                $start = $open;
            }
        }
        if ($depth !== 0) {
            return false;
        }
        return $tokens;
    }

    private function getTranslationMessages(string $domain): array
    {
        $cachePrivider = new Pool($this->driver);
        $location = $this->getLocale();
        return $cachePrivider->getTranslations($domain, $location);
    }
}
