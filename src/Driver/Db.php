<?php
declare(strict_types=1);

namespace mrsatik\Language\Driver;
use PDO;

class Db implements DbInterface
{
    /**
     * @var PDO
     */
    private $connect;

    public function __construct(PDO $connect)
    {
        $this->connect = $connect;
    }

    /**
     * {@inheritDoc}
     */
    public function getTranslations(string $module, string $lang): array
    {
        $sql = 'SELECT c.code, v.value
FROM tnLanguagePhraseValue AS v
INNER JOIN tnLanguagePhraseCode AS c ON(c.id=v.languagePhraseCodeId)
INNER JOIN  tnLanguagePhraseModule AS pm ON(c.moduleId=pm.id)
INNER JOIN tnLanguage AS l ON(l.id=v.languageId)
WHERE pm.module=\'%s\' AND l.locale=\'%s\'';
        $stmt = $this->connect->query(\sprintf($sql, $module, $lang));

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
