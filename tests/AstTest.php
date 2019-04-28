<?php declare(strict_types=1);

namespace Yay;

/**
 * @group small
 */
class AstTest extends \PHPUnit\Framework\TestCase {

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
        $this->expectException(YayPreprocessorError::class);
        $this->expectExceptionMessageRegExp("/^Ast cannot be casted to '{$typeName}'$/");
        $ast = new Ast('', ['defined' => true]);
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
        $ast = new Ast('', [
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
        $ast = new Ast('', [
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

    function testAstSet() {
        $ast = new Ast('label', [
            'deep' => [
                'token' => new Token(T_STRING, 'foo'),
                'deeper' => [
                    'tokens' => [0 => new Token(T_STRING, 'bar'), 1 => new Token(T_STRING, 'baz')],
                ],
            ],
        ]);

        $ast->set('deep token', $patchedFooToken = new Token(T_STRING, 'patched_foo'));

        $ast->{'deep deeper tokens 0'} = $patchedBarToken = new Token(T_STRING, 'patched_bar');
        $ast->{'deep deeper tokens 1'} = $patchedBazToken = new Token(T_STRING, 'patched_baz');

        $expected = [
            'deep' => [
                'token' => $patchedFooToken,
                'deeper' => [
                    'tokens' => [0 => $patchedBarToken, 1 => $patchedBazToken],
                ],
            ],
        ];

        $this->assertSame($expected, $ast->unwrap());
        $this->assertSame($expected, $ast->array());
    }

    function testAstHiddenNodes() {
        $exposed = new Token(T_STRING, 'exposed');
        $hidden = new Token(T_STRING, '_hidden');
        $ast = new Ast('', [
            'exposed' => $exposed,
            '_hidden' => $hidden,
            'deep' => [
                'exposed' => $exposed,
                '_hidden' => $hidden,
            ]
        ]);
        $this->assertSame([$exposed, $exposed], $ast->tokens());
        $this->assertSame('exposed,exposed', $ast->implode(','));
    }
}
