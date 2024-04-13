<?php
declare(strict_types=1);
namespace mrsatik\LanguageTest;

use PHPUnit\Framework\TestCase;
use mrsatik\Language\Translator;
use mrsatik\Settings\Settings;
use Symfony\Component\Translation\TranslatorInterface as OrigTranslatorInterface;

class TranslatorTest extends TestCase
{
    /**
     * @dataProvider getGetTranslateDataProvider
     */
    public function testTranslatorInstance(array $result)
    {
        $translator = new Translator(Settings::getInstance()->getValue('common.lang.default'), $this->getDbStub($result));
        foreach ($result as $k => $actual) {
            $resultData = $translator->t($actual['code'], [], 'test' . preg_replace('/[^0-9]/is', '', microtime()));
            $this->assertEquals($resultData, $actual['value']);
        }
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

    /**
     * @dataProvider getGetTranslateParamsDataProvider
     */
    public function testTranslatorParams(array $result)
    {
        foreach ($result as $k => $testValue) {
            $translator = new Translator(Settings::getInstance()->getValue('common.lang.default'), $this->getDbStub([[
                'code' => 'message',
                'value' => $testValue['message'],
            ]]));
            $resultTest = $translator->t('message', $testValue['param'], 'test' . $k .  preg_replace('/[^0-9]/is', '', microtime()));

            $this->assertEquals($resultTest, $testValue['messageActual']);
        }
    }

    /**
     * @dataProvider getGetTranslateParamsDataProvider
     */
    public function testMessages(array $result)
    {
        foreach ($result as $k => $formatedMeddages) {
            $translator = new Translator(Settings::getInstance()->getValue('common.lang.default'), $this->getDbStub([]));
            $resultTest = $translator->message($formatedMeddages['message'], $formatedMeddages['param']);
            $this->assertEquals($resultTest, $formatedMeddages['messageActual']);
        }
    }

    /**
     * @dataProvider getGetTranslateDataProvider
     */
    public function testResources(array $result)
    {
        $translator = new Translator(Settings::getInstance()->getValue('common.lang.default'), $this->getDbStub($result));
        $messages = $translator->getResources('test' . preg_replace('/[^0-9]/is', '', microtime()));
        $compare = [];
        foreach ($result as $item) {
            $compare[$item['code']] = $item['value'];
        }
        $this->assertEquals($compare, $messages);
    }

    public function testGetLocate()
    {
        $actual = Settings::getInstance()->getValue('common.lang.default');
        $translator = new Translator($actual, $this->getDbStub([]));
        $result = $translator->getLocale();

        $locale = Settings::getInstance()->getValue('common.lang.locale');
        $this->assertNotEquals($result, $actual);
        $this->assertEquals($locale[$actual], $result);
    }

    public function testGetTranslator()
    {
        $translator = new Translator(Settings::getInstance()->getValue('common.lang.default'), $this->getDbStub([]));
        $traslatorInstance = $translator->getTranslator();
        $this->assertInstanceOf(OrigTranslatorInterface::class, $traslatorInstance);
    }

    public function testIsDefault()
    {
        $translator = new Translator(Settings::getInstance()->getValue('common.lang.default'), $this->getDbStub([]));
        $isDefault = $translator->isDefaultLang();
        $this->assertTrue($isDefault);

        $locale = Settings::getInstance()->getValue('common.lang.locale');
        $notDefault = null;
        foreach ($locale as $k => $v) {
            if ($k !== Settings::getInstance()->getValue('common.lang.default')) {
                $notDefault = $k;
                break;
            }
        }
        $translator = new Translator($notDefault, $this->getDbStub([]));
        $isDefault = $translator->isDefaultLang();
        $this->assertFalse($isDefault);
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
            ]],
            [[
                [
                    'code' => '1',
                    'value' => '4',
                ],
                [
                    'code' => '2',
                    'value' => 5,
                ],
                [
                    'code' => '3',
                    'value' => 7,
                ]
            ]],
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
            ]],
        ];
    }

    public function getGetTranslateParamsDataProvider()
    {
        return [
            [[
                [
                    'message' => 'test {foo} test',
                    'param' => ['foo' => 'bar'],
                    'messageActual' => 'test bar test',
                ],
                [
                    'message' => 'test2 {foo} test2',
                    'param' => ['foo' => 'bar'],
                    'messageActual' => 'test2 bar test2',
                ],
            ]],
        ];
    }
}