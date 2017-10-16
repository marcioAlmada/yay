<?php declare(strict_types=1);

namespace Yay;

/**
 * @group small
 */
class AstTest extends \PHPUnit_Framework_TestCase {

    function testAstFetch() {
        $ast = new Ast('foo', ['bar' => ['baz' => true, 'buz' => false]]);

        $this->assertSame('foo', $ast->label());
        $this->assertSame(['bar'], $ast->symbols());

        $childAst = $ast->{'* foo bar'};
        $this->assertInstanceOf(Ast::class, $childAst);
        $this->assertSame('bar', $childAst->label());
        $this->assertSame([], $childAst->symbols());
        $this->assertSame(null, $childAst->unwrap());
        $this->assertNull($childAst->{'baz'});
        $this->assertNull($childAst->{'buz'});
        $this->assertNull($childAst->{'undefined'});

        $childAst = $ast->{'* bar'};
        $this->assertInstanceOf(Ast::class, $childAst);
        $this->assertSame('bar', $childAst->label());
        $this->assertSame(['baz', 'buz'], $childAst->symbols());
        $this->assertSame(['baz' => true, 'buz' => false], $childAst->unwrap());
        $this->assertTrue($childAst->{'baz'});
        $this->assertFalse($childAst->{'buz'});
        $this->assertNull($childAst->{'undefined'});
    }

    function providerForTestMapAstCastOnFailure() {
        return [
            ['* defined', 'null', 'null'],
            ['* undefined', 'bool', 'boolean'],
            ['* undefined', 'array', 'array'],
            ['* undefined', 'token', preg_quote(Token::class)],
        ];
    }

    /**
     * @dataProvider providerForTestMapAstCastOnFailure
     */
    function testMapAstCastOnFailure(string $path, string $castMethod, string $typeName) {
        $this->setExpectedExceptionRegExp(YayException::class, "/^Ast cannot be casted to '{$typeName}'$/");
        $ast = new Ast(null, ['defined' => true]);
        var_dump($ast->{$path}->$castMethod());
    }

    function providerForTestAstCast() {
        return [
            ['* some null', 'null', null],
            ['* some boolean', 'bool', true],
            ['* some string', 'string', 'foo'],
            ['* some array', 'array', ['foo', 'bar']],
            ['* some token', 'token', new Token(';')],
            ['* some tokens', 'tokens', []],
        ];
    }

    /**
     * @dataProvider providerForTestAstCast
     */
    function testAstCast(string $path, string $castMethod, $expected) {
        $ast = new Ast(null, [
            'some' => [
                'null' => null,
                'boolean' => true,
                'string' => 'foo',
                'array' => ['foo', 'bar'],
                'token' => new Token(';'),
                'tokens' => ['deep' => ['inside' => []]],
            ]
        ]);

        if ($expected instanceof Token)
            $this->assertEquals((string) $expected, (string) $ast->{$path}->$castMethod());
        else
            $this->assertEquals($expected, $ast->{$path}->$castMethod());
    }

    function testAstFlattenning() {
        $ast = new Ast(null, [
            'deep' => [
                'token' => $token1 = new Token(T_STRING, 'foo'),
                'deeper' => [
                    'token' => $token2 = new Token(T_STRING, 'bar'),
                ],
            ]
        ]);

        $this->assertEquals([$token1, $token2], $ast->tokens());

        $flattened = $ast->flatten();

        $this->assertInstanceOf(Ast::class, $flattened);

        $this->assertEquals([$token1, $token2], $flattened->tokens());

        $this->assertEquals([$token1, $token2], $flattened->unwrap());

        $this->assertEquals([$token1, $token2], $flattened->array());
    }
}
