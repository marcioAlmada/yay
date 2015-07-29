<?php declare(strict_types=1);

namespace Yay;

/**
 * @group small
 */
class TokenStreamTest extends \PHPUnit_Framework_TestCase
{

    function testStep() {
        $ts = TokenStream::fromSource("<?php  :  \n  :: ");

        $this->assertTrue($ts->step()->is(T_WHITESPACE));
        $this->assertTrue($ts->step()->is(':'));
        $this->assertTrue($ts->step()->is(T_WHITESPACE));
        $this->assertTrue($ts->step()->is(T_DOUBLE_COLON));
        $this->assertTrue($ts->step()->is(T_WHITESPACE));
        $this->assertFalse($ts->step());
    }

    function testNext() {
        $ts = TokenStream::fromSource("<?php  : \n :: ");

        $this->assertTrue($ts->next()->is(':'));
        $this->assertTrue($ts->next()->is(T_DOUBLE_COLON));
        $this->assertFalse($ts->next());
    }

    function providerForTestLoop() {
        return [
            [TokenStream::fromSource('<?php no trailing whitespace')],
            [TokenStream::fromSource('<?php with trailing whitespace ')],
            [TokenStream::fromSource("<?php // comment \n ?> HTML <?php ")],
        ];
    }

    /**
     * @dataProvider providerForTestLoop
     */
    function testLoop(TokenStream $ts) {
        while($ts->next());
        $this->assertFalse($ts->current(), 'EOF was not reach.');
    }

    function testClone() {
        $tsa = TokenStream::fromSource('<?php ');
        $tsb = clone $tsa;

        $tsa->current()->__construct(T_INLINE_HTML, 'PHP');

        $this->assertNotEquals((string) $tsa, (string) $tsb);
    }
}
