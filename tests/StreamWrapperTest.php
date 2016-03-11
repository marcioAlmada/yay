<?php declare(strict_types=1);

namespace Yay;

/**
 * @group large
 */
class StreamWrapperTest extends \PHPUnit_Framework_TestCase {

    const
        FIXTURES_DIR = 'fixtures/wrapper',
        ABSOLUTE_FIXTURES_DIR = __DIR__ . '/' . self::FIXTURES_DIR
    ;

    function setUp() { StreamWrapper::register(); }

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

    function testStreamWrapperInclusionRelative() {
        include 'yay://' . self::FIXTURES_DIR . '/type_alias.php';
        $result = \Yay\Fixtures\Wrapper\test_type_alias(__FILE__);
        $this->assertEquals('pass', $result);
    }

    function testStreamWrapperInclusionAbsolute() {
        include 'yay://' . self::ABSOLUTE_FIXTURES_DIR . '/type_alias_absolute.php';
        $result = \Yay\Fixtures\Wrapper\test_type_alias_absolute(__FILE__);
        $this->assertEquals('pass', $result);
    }

   function tearDown() { StreamWrapper::unregister(); }
}
