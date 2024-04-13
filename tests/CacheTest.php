<?php
declare(strict_types=1);

namespace mrsatik\LanguageTest;

use PHPUnit\Framework\TestCase;
use mrsatik\Language\Cache\Pool;
use mrsatik\Language\Driver\Db;

class CacheTest extends TestCase
{
    /**
     * @dataProvider getGetTranslateDataProvider
     */
    public function testExpireCache(array $result, $module, $lang)
    {
        $driver = new Db($this->getDbStub($result));
        $pool = new Pool($driver);
        $test = $pool->getTranslations($module, $lang);
        $expireResult = $pool->expireCache($module, $lang);
        $this->assertTrue($expireResult);
    }

    /**
     * @codeCoverageIgnore
     */
    private function getDbStub($result)
    {
        $STMTstub = $this->getMockBuilder('PDOStatement')->getMock();
        $STMTstub->expects($this->any())
            ->method('fetchAll')
            ->will($this->returnValue($result));

        $PDOstub = $this->getMockBuilder('PDO')->disableOriginalConstructor()->getMock();
        $PDOstub
            ->expects($this->any())
            ->method('query')
            ->will($this->returnValue($STMTstub));

        return $PDOstub;
    }

    public function getGetTranslateDataProvider()
    {
        return [
            [[
                [
                    'code' => 'a',
                    'value' => 'b',
                ],
                [
                    'code' => 'foo',
                    'value' => 'bar',
                ],
                [
                    'code' => 'bar',
                    'value' => 'foo',
                ]
            ], 'module', 'lang'],
            [[
                [
                    'code' => '1',
                    'value' => '4',
                ],
                [
                    'code' => 2,
                    'value' => 5,
                ],
                [
                    'code' => 3,
                    'value' => 7,
                ]
            ], 'module_test', 'ru_RU'],
            [[
                [
                    'code' => 'z',
                    'value' => '3',
                ],
                [
                    'code' => 'test test test',
                    'value' => 'bar',
                ],
                [
                    'code' => 'test_223',
                    'value' => 'foo',
                ]
            ], 'module_test2', 'en_US'],
        ];
    }
}