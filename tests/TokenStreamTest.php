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
        $this->assertNull($ts->step());
    }

    function testNext() {
        $ts = TokenStream::fromSource("<?php  : \n :: ");

        $this->assertTrue($ts->next()->is(':'));
        $this->assertTrue($ts->next()->is(T_DOUBLE_COLON));
        $this->assertNull($ts->next());
    }

    function testReset() {
        $ts = TokenStream::fromSource("<?php 2 4 6 8");
        while($ts->next());

        $this->assertNull($ts->current());
        $this->assertNull($ts->reset());
        $this->assertEquals('<?php ', (string) $ts->current());
    }

    function providerForTestTrim() {
        return [
            ['<?php {A B C}', '{A B C}'],
            ['<?php      {A B C}', '{A B C}'],
            ['<?php {A B C}     ', '{A B C}'],
            ["<?php \t\n{A B C}\t\n\t", '{A B C}'],
            ['<?php ', ''],
        ];
    }

    /**
     * @dataProvider providerForTestTrim
     */
    function testTrim(string $src, string $expected) {
        $ts = TokenStream::fromSource($src);
        $ts->shift();
        $ts->trim();
        $this->assertEquals($expected, (string) $ts);
    }

    function providerForTestLoop() {
        return [
            ['<?php no trailing whitespace'],
            ['<?php with trailing whitespace '],
            ["<?php // comment \n ?> HTML <?php "],
        ];
    }

    /**
     * @dataProvider providerForTestLoop
     */
    function testLoop(string $src) {
        $ts = TokenStream::fromSource($src);
        while($ts->next());
        $this->assertNull($ts->current(), 'EOF was not reach.');
        $this->assertEquals($src, (string) $ts);
        $this->assertNull($ts->current(), 'Index was not preserved.');
    }

    function testClone() {
        $tsa = TokenStream::fromSource('<?php START END');
        $tsb = clone $tsa;
        $tsb->reset();
        $this->assertNotSame($tsa->index(), $tsb->index());
    }

    function testInject() {
        $ts = TokenStream::fromSource("<?php START END ");

        $ts->inject(TokenStream::fromEmpty());
        $this->assertEquals('<?php START END ', (string) $ts);

        $ts->step();
        $ts->step();
        $ts->inject(TokenStream::fromSequence(
            [T_STRING, 'MIDDLE', 0], [T_WHITESPACE, '  ', 0]));
        $this->assertEquals('<?php START MIDDLE  END ', (string) $ts);
        $ts->reset();

        $ts = TokenStream::fromEmpty();
        $ts->inject(TokenStream::fromSequence(
            [T_OPEN_TAG, '<?php', 0], [T_WHITESPACE, ' ', 0], [T_STRING, 'A', 0]));
        $this->assertEquals('<?php A', (string) $ts);
    }

    function testPush() {
        $ts = TokenStream::fromSource("<?php 1 2 3 ");

        $ts->push(new Token(T_LNUMBER, '4'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $ts->push(new Token(T_LNUMBER, '5'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $this->assertEquals('<?php 1 2 3 4 5 ', (string) $ts);

        $ts = TokenStream::fromEmpty();
        $ts->push(new Token(T_STRING, 'A'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $ts->push(new Token(T_STRING, 'B'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $this->assertEquals('A B ', (string) $ts);

        $ts->extract($ts->index(), $ts->index()->next->next->next);
        $this->assertEquals(' ', (string) $ts);

        $ts->push(new Token(T_STRING, 'C'));
        $this->assertEquals(' C', (string) $ts);
    }
}
