<?php declare(strict_types=1);

namespace Yay;

/**
 * @group large
 */
class StreamWrapperTest extends \PHPUnit_Framework_TestCase {

    const
        FIXTURES_DIR = 'fixtures',
        ABSOLUTE_FIXTURES_DIR = __DIR__ . '/' . self::FIXTURES_DIR
    ;

    static function setupBeforeClass() { StreamWrapper::register(); }

    function syntaxErrorProvider() {

        $fixtures = [
            ['/syntax_error.php', "syntax error, unexpected '}'"],
            ['/preprocessor_error.php', "Undefined token type 'T_HAKUNAMATATA'"]
        ];

        foreach ($fixtures as list($file, $message)) {
            yield [self::FIXTURES_DIR . $file, $message];
            yield [self::ABSOLUTE_FIXTURES_DIR . $file, $message];
        }
    }

    /**
     * @dataProvider syntaxErrorProvider
     */
    function testStreamWrapperOnSyntaxError(string $file, string $error) {
        $this->setExpectedException(\ParseError::class, $error);
        include 'yay://' . $file;
    }

    function fixtureProvider() {
        $fixtures = [
            '/type_alias.php'
        ];

        foreach ($fixtures as $file) {
            yield [self::FIXTURES_DIR . $file];
            yield [self::ABSOLUTE_FIXTURES_DIR . $file];
        }
    }

    /**
     * @dataProvider fixtureProvider
     * @runInSeparateProcess
     */
    function testStreamWrapperInclusion(string $file) {
        include 'yay://' . $file;
        $result = \Yay\Fixtures\test_type_alias(__FILE__);
        $this->assertEquals('pass', $result);
    }

    static function tearDownAfterClass() { StreamWrapper::unregister(); }
}
