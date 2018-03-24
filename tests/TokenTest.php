<?php declare(strict_types=1);

namespace Yay;

/**
 * @group small
 */
class TokenTest extends \PHPUnit\Framework\TestCase {
    function equalsProvider() : array {
        return [
            [
                [T_STRING, '"yep"'],
                [T_STRING, '"nope"'],
                false
            ],[
                [T_STRING, '""'],
                [':'],
                false
            ],[
                [T_STRING, '"any"'],
                [T_STRING],
                true
            ],[
                [T_STRING, "·"],
                [T_STRING, '·'],
                true
            ],[
                [T_STRING, "·"],
                [T_STRING],
                true
            ],[
                [T_FUNCTION, 'function'],
                [T_FUNCTION],
                true
            ],[
                [T_FUNCTION],
                [T_FUNCTION, 'function'],
                true
            ],
        ];
    }

    /**
     * @dataProvider equalsProvider
     */
    function testEquals($a, $b, bool $assertion) {
        $token_a = new Token(...$a);
        $token_b = new Token(...$b);
        $this->assertEquals(
            $assertion
            ,
            $token_a->equals($token_b)
            ,
            "{$token_a->dump()} !== {$token_b->dump()}"
        );
    }

    function testIs() {
        $token = new Token('$');
        $this->assertTrue($token->is('$'));
        $this->assertFalse($token->is('!'));

        $token = new Token(T_STRING);
        $this->assertFalse($token->is(T_OPEN_TAG));
        $this->assertTrue($token->is(T_STRING));

        $token = new Token(T_STRING, '"value"', null);
        $this->assertFalse($token->is(T_OPEN_TAG));
        $this->assertTrue($token->is(T_STRING));
    }
}
