<?php declare(strict_types=1);

namespace Yay;

/**
 * @group small
 */
class TokenStreamTest extends \PHPUnit_Framework_TestCase
{

    function testToString() {
        $ts = TokenStream::fromSource('<?php A B C D E F G ');
        $this->assertEquals('<?php A B C D E F G ', (string) $ts);

        $ts = TokenStream::fromSource('');
        $this->assertEquals('', (string) $ts);
    }

    function testIsEmpty() {
        $ts = TokenStream::fromSource('');
        $this->assertTrue($ts->isEmpty());

        $ts = TokenStream::fromSource('<?php ');
        $this->assertFalse($ts->isEmpty());
    }

    function testStep() {
        $ts = TokenStream::fromSource("<?php A B   C D \n E \n\n F");

        $this->assertSame('<?php ', (string) $ts->current());
        $this->assertSame('A', (string) $ts->step());
        $this->assertSame(' ', (string) $ts->step());
        $this->assertSame('B', (string) $ts->step());
        $this->assertSame('   ', (string) $ts->step());
        $this->assertSame('C', (string) $ts->step());
        $this->assertSame(' ', (string) $ts->step());
        $this->assertSame('D', (string) $ts->step());
        $this->assertSame(" \n ", (string) $ts->step());
        $this->assertSame('E', (string) $ts->step());
        $this->assertSame(" \n\n ", (string) $ts->step());
        $this->assertSame('F', (string) $ts->step());
        $this->assertSame(null, $ts->step());
        $this->assertSame(null, $ts->current());
        $this->assertSame(null, $ts->step());
        $this->assertSame(null, $ts->current());
    }

    function testBack() {
        $ts = TokenStream::fromSource("<?php A B");

        $this->assertSame('<?php ', (string) $ts->current());
        $this->assertSame('A', (string) $ts->step());
        $this->assertSame(' ', (string) $ts->step());
        $this->assertSame('B', (string) $ts->step());
        $this->assertSame(' ', (string) $ts->back());
        $this->assertSame('A', (string) $ts->back());
        $this->assertSame('<?php ', (string) $ts->back());
    }


    function testNext() {
        $ts = TokenStream::fromSource("<?php 1 2   3 4 \n 5 \n\n 6");

        $this->assertSame('<?php ', (string) $ts->current());
        $this->assertSame('1', (string) $ts->next());
        $this->assertSame('2', (string) $ts->next());
        $this->assertSame('3', (string) $ts->next());
        $this->assertSame('4', (string) $ts->next());
        $this->assertSame('5', (string) $ts->next());
        $this->assertSame('6', (string) $ts->next());
        $this->assertSame(null, $ts->next());
        $this->assertSame(null, $ts->current());
        $this->assertSame(null, $ts->next());
        $this->assertSame(null, $ts->current());
    }

    function testReset() {
        $ts = TokenStream::fromSource("<?php 2 4 6 8");

        while($ts->next());

        $this->assertNull($ts->current());

        $ts->reset();

        $this->assertEquals('<?php ', (string) $ts->current());
    }

    function providerForTestTrim() {
        return [
            ['{A B C}', '{A B C}'],
            ['      {A B C}', '{A B C}'],
            ['{A B C}     ', '{A B C}'],
            ['      {A B C}     ', '{A B C}'],
            ["\t\n\t{A B C}\t\n\t", '{A B C}'],
            [' ', ''],
        ];
    }

    /**
     * @dataProvider providerForTestTrim
     */
    function testTrim(string $src, string $expected) {
        $ts = TokenStream::fromSource('<?php ' . $src);
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
        $this->assertNull($ts->current(), 'Index was not preserved after string conversion.');
    }

    function testClone() {
        $tsa = TokenStream::fromSource('<?php START END');
        $tsb = clone $tsa;
        $tsb->reset();
        $this->assertNotSame($tsa->index(), $tsb->index());
    }

    function testExtract() {
        $ts = TokenStream::fromSequence(
            [T_STRING, 'T_VARIABLE·A', 0], [T_WHITESPACE, ' ', 0], [T_STRING, 'T_VARIABLE·B', 0]);

        $ts->extract($ts->index(), $ts->index()->next);

        $this->assertEquals(' T_VARIABLE·B', (string) $ts);
    }

    function testInject() {
        $ts = TokenStream::fromSource('<?php START END');
        $ts->next();
        $ts->step();
        $ts->inject(TokenStream::fromSequence(
            [T_STRING, 'MIDDLE_B', 0], [T_WHITESPACE, '  ', 0]));
        $ts->inject(TokenStream::fromSequence(
            [T_STRING, 'MIDDLE_A', 0], [T_WHITESPACE, '  ', 0]));
        $this->assertEquals('<?php START MIDDLE_A  MIDDLE_B  END', (string) $ts);

        $ts = TokenStream::fromSource('');
        $ts->inject(TokenStream::fromSequence(
            [T_WHITESPACE, '  ', 0], [T_STRING, 'BAR', 0], [T_WHITESPACE, '  ', 0]));
        $this->assertEquals('  BAR  ', (string) $ts);

        $ts->inject(TokenStream::fromSequence(
            [T_WHITESPACE, '  ', 0], [T_STRING, 'FOO', 0], [T_WHITESPACE, '  ', 0]));
        $this->assertEquals('  FOO    BAR  ', (string) $ts);

        $ts = TokenStream::fromSource('<?php A B');
        $ts->next();
        $ts->next();
        $ts->next();
        $node = $ts->index();
        $partial = TokenStream::fromSequence(
            [T_WHITESPACE, '  ', 0], [T_STRING, 'C', 0], [T_WHITESPACE, '  ', 0]);
        $index = $partial->index();
        $ts->inject($partial);
        $this->assertEquals('<?php A B  C  ', (string) $ts);
        // $this->assertSame($index, $ts->index());
    }

    function testPush() {
        $ts = TokenStream::fromSource("<?php 1 2 3 ");

        $ts->push(new Token(T_LNUMBER, '4'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $ts->push(new Token(T_LNUMBER, '5'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $this->assertEquals('<?php 1 2 3 4 5 ', (string) $ts);

        $ts = TokenStream::fromSource('<?php ');
        $ts->push(new Token(T_STRING, 'A'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $ts->push(new Token(T_STRING, 'B'));
        $ts->push(new Token(T_WHITESPACE, ' '));
        $this->assertEquals('<?php A B ', (string) $ts);
    }

    function testEach() {
        $ts = TokenStream::fromSource('<?php 5 5 5 5 5');
        $sum = 0;
        $ts->each(function(Token $t) use(&$sum) {
            if ($t->is(T_LNUMBER)) $sum += (int) (string) $t;
        });
        $this->assertEquals(25, $sum);
    }
}
