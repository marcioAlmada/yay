<?php declare(strict_types=1);

namespace Yay;

/**
 * @group large
 */
class MacroScopeTest extends \PHPUnit_Framework_TestCase {

    const
        FIXTURES_DIR = 'fixtures/scope',
        ABSOLUTE_FIXTURES_DIR = __DIR__ . '/' . self::FIXTURES_DIR
    ;

    function setUp() {
        StreamWrapper::register(new Engine);
    }

    function testGlobalMacro() {
        include 'yay://' . self::ABSOLUTE_FIXTURES_DIR . '/macros.php';
        $result = include 'yay://' . self::ABSOLUTE_FIXTURES_DIR . '/test_global.php';
        $this->assertTrue($result);
    }

    function testLocalMacro() {
        include 'yay://' . self::ABSOLUTE_FIXTURES_DIR . '/macros.php';
        $result = include 'yay://' . self::ABSOLUTE_FIXTURES_DIR . '/test_local.php';
        $this->assertEquals('LOCAL_MACRO', $result);
    }

    function tearDown() { StreamWrapper::unregister(); }
}
