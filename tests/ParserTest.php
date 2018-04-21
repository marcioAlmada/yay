<?php declare(strict_types=1);

namespace Yay;

/**
 * @group small
 */
class ParserTest extends \PHPUnit\Framework\TestCase {

    function setUp()
    {
        if ((bool) getenv('YAY_CLI_PARSER_TRACER')) Parser::setTracer(new \Yay\ParserTracer\CliParserTracer);
    }

    function tearDown()
    {
        Parser::setTracer(new \Yay\ParserTracer\NullParserTracer);
    }

    protected function parseHalt(TokenStream $ts, Parser $parser, $msg) {
        $this->expectException(Halt::class);
        $this->expectExceptionMessage(implode(PHP_EOL, (array) $msg));

        $current = $ts->current();

        try {
            $parser->onCommit(
                function($commit) use($parser) {
                    $this->fail("Unexpected commit on {$parser}().");
                }
            )
            ->withErrorLevel(Error::ENABLED)
            ->parse($ts);
        }
        catch (Halt $e) {
            $this->assertSame($current, $ts->current(), 'Failed to backtrack.');

            throw $e;
        }
    }

    protected function parseError(TokenStream $ts, Parser $parser, $msg) {
        $current = $ts->current();
        $result = $parser->onCommit(
            function($commit) use($parser) {
                $this->fail("Unexpected commit on {$parser}().");
            }
        )
        ->withErrorLevel(Error::ENABLED)
        ->parse($ts);

        $this->assertSame($current, $ts->current());
        $this->assertInstanceOf(Error::class, $result);
        $this->assertSame(implode(PHP_EOL, (array) $msg), $result->message());
    }

    protected function parseSuccess(TokenStream $ts, Parser $parser, string $expected = null) : Ast {
        $commited = false;
        $ast = $parser->onCommit(
            function($ast) use (&$commited){
                $commited = true;
            }
        )
        ->withErrorLevel(Error::ENABLED)
        ->parse($ts);

        $this->assertTrue($commited, "Missing commit on {$parser}().");

        $tokens = array_map(function(token $t) { return $t->dump(); }, $ast->tokens());

        if ($expected) $this->assertEquals($expected, implode(', ', $tokens));

        return $ast;
    }

    protected function assertRaytrace(string $expected, Parser $parser) {
        $this->assertEquals($expected, $parser->expected()->raytrace());
    }

    function testToken() {
        $ts = TokenStream::fromSource("<?php _step 1 step_ 2 end");

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess($ts, token(T_STRING, '_step'), "T_STRING(_step)");
        $this->parseSuccess($ts, token(T_LNUMBER, '1'), "T_LNUMBER(1)");
        $this->parseSuccess($ts, token(T_STRING), "T_STRING(step_)");
        $this->parseSuccess($ts, token(T_LNUMBER), "T_LNUMBER(2)");
    }

    function testOutOfBoundsToken() {
        $ts = TokenStream::fromSource("<?php \n foo");

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess($ts, token(T_STRING, 'foo'), "T_STRING(foo)");

        $this->parseError(
            $ts,
            token(';'),
            "Unexpected end at T_STRING(foo) on line 2, expected ';'."
        );
    }

    function testTokenOnError() {
        $ts = TokenStream::fromSource("<?php ba");

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            token(T_STRING, 'bar'),
            "Unexpected T_STRING(ba) on line 1, expected T_STRING(bar)."
        );
    }

    function testRtoken() {
        $ts = TokenStream::fromSource("<?php T_TEST ");

        $this->parseSuccess($ts, rtoken('/<\?php /'), "T_OPEN_TAG(<?php )");
        $this->parseSuccess($ts, rtoken('/^T_\w+$/'), "T_STRING(T_TEST)");
    }

    function testRtokenOnError() {
        $ts = TokenStream::fromSource("<?php T_ T_TEST ");

        $this->parseSuccess($ts, rtoken('/<\?php /'), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            rtoken('/^T_\w+$/'),
            "Unexpected T_STRING(T_) on line 1, expected T_STRING(matching '/^T_\w+$/')."
        );
    }

    function testAny() {
        $ts = TokenStream::fromSource("<?php 1 two ");
        $parser = any();

        $this->parseSuccess($ts, $parser, "T_OPEN_TAG(<?php )");
        $this->parseSuccess($ts, $parser, "T_LNUMBER(1)");
        $this->parseSuccess($ts, $parser, "T_STRING(two)");
    }

    function testOutOfBoundsAny() {
        $ts = TokenStream::fromSource("<?php end");
        $parser = any();

        $this->parseSuccess($ts, $parser, "T_OPEN_TAG(<?php )");
        $this->parseSuccess($ts, $parser, "T_STRING(end)");
        $this->parseError(
            $ts,
            $parser,
            "Unexpected end at T_STRING(end) on line 1, expected ANY()."
        );
    }

    function testBuffer() {
        $ts = TokenStream::fromSource('<?php $a <~> $b');

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess(
            $ts,
            chain(
                token(T_VARIABLE),
                buffer('<~>'),
                token(T_VARIABLE)
            ),
            "T_VARIABLE(\$a), '<', '~', '>', T_VARIABLE(\$b)"
        );

        $ts = TokenStream::fromSource('<?php \n');

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess(
            $ts,
            buffer('\n'),
            'T_NS_SEPARATOR(\), T_STRING(n)'
        );
    }

    function testBufferError() {
        $ts = TokenStream::fromSource('<?php < ~>');

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            buffer('<~>'),
            "Unexpected T_WHITESPACE( ) on line 1, expected BUFFER(<~>)."
        );
    }

    function testBufferOnEnd() {
        $ts = TokenStream::fromSource('<?php <~>');

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess(
            $ts,
            buffer('<~>'),
            "'<', '~', '>'"
        );
    }

    function testOptional() {
        $ts = TokenStream::fromSource("<?php foo bar baz ");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        $this->parseSuccess($ts, optional(token(T_STRING, 'foo')), "T_STRING(foo)");
        $this->parseSuccess($ts, optional(token(T_STRING, 'foo')), "");

        $this->parseSuccess($ts, optional(token(T_STRING, 'bar')), "T_STRING(bar)");

        $this->parseSuccess($ts, optional(chain(token(T_STRING), token(T_STRING))), "");

        $this->parseSuccess($ts, optional(token(T_STRING, 'baz')), "T_STRING(baz)");
        $this->parseSuccess($ts, optional(token(T_STRING, 'baz')), "");
    }

    function testNot() {
        $ts = TokenStream::fromSource("<?php foo bar null ");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess($ts, repeat(chain(not(token(T_STRING, 'null')), token(T_STRING))), "T_STRING(foo), T_STRING(bar)");

        $ts = TokenStream::fromSource("<?php foo bar null baz");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess($ts, repeat(chain(not(token(T_STRING, 'null')), token(T_STRING))), "T_STRING(foo), T_STRING(bar)");

        $ts = TokenStream::fromSource("<?php null foo bar");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            repeat(chain(not(token(T_STRING, 'null')), token(T_STRING))),
            "Unexpected T_STRING(null) on line 1, expected not T_STRING(null)."
        );

        $ts = TokenStream::fromSource("<?php null foo bar");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            repeat(chain(not(either(token(T_STRING, 'null'), token(T_STRING, 'true'), token(T_STRING, 'false'))), token(T_STRING))),
            "Unexpected T_STRING(null) on line 1, expected not T_STRING(null) or not T_STRING(true) or not T_STRING(false)."
        );
    }

    function testRepeat() {
        $ts = TokenStream::fromSource("<?php foo bar baz 1 2 3 @ ");

        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess(
            $ts,
            repeat(token(T_STRING)),
            "T_STRING(foo), T_STRING(bar), T_STRING(baz)"
        );

        $this->parseSuccess(
            $ts,
            repeat(any()),
            "T_LNUMBER(1), T_LNUMBER(2), T_LNUMBER(3), '@'"
        );
    }

    function testRepeatWithFailure() {
        $ts = TokenStream::fromSource("<?php 1 2 3");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError($ts, repeat(token(T_STRING)), "Unexpected T_LNUMBER(1) on line 1, expected T_STRING().");
    }

    function testRepeatWithHalt() {
        $ts = TokenStream::fromSource("<?php 1 2 3");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            repeat(
                chain(
                    token(T_LNUMBER),
                    token(T_STRING)
                )
            ),
            "Unexpected T_LNUMBER(2) on line 1, expected T_STRING()."
        );
    }

    function testRepeatOnBranching() {
        $ts = TokenStream::fromSource("<?php 1 2 3");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            repeat(either(token(T_STRING), token(';'))),
            "Unexpected T_LNUMBER(1) on line 1, expected T_STRING() or ';'."
        );
    }

    function testRepeatOnEof() {
        $ts = TokenStream::fromSource("<?php ");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            repeat(token(T_STRING)),
            "Unexpected end at T_OPEN_TAG(<?php ) on line 1, expected T_STRING()."
        );
    }

    function testChain() {
        $ts = TokenStream::fromSource("<?php foo { } bar { } end");

        $this->parseSuccess(
            $ts,
            chain(
                token(T_OPEN_TAG),
                token(T_STRING),
                token('{'),
                token('}'),
                token(T_STRING),
                token('{'),
                token('}')
            ),
            "T_OPEN_TAG(<?php ), T_STRING(foo), '{', '}', T_STRING(bar), '{', '}'"
        );

        $this->assertEquals('T_STRING(end)', $ts->current()->dump());
    }

    function testChainOnFailure() {
        $ts = TokenStream::fromSource("<?php ~ 2 3");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        $this->parseError(
            $ts,
            chain(
                token(T_LNUMBER, '1'),
                token(T_LNUMBER, '2'),
                token(T_LNUMBER, '3')
            ),
            "Unexpected '~' on line 1, expected T_LNUMBER(1)."
        );
    }

    function testChainOnFailureWithOptionals() {
        $ts = TokenStream::fromSource("<?php ~ 1 2 3");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        $this->parseError(
            $ts,
            chain(
                optional(token('+')),
                optional(token('-')),
                token(T_LNUMBER, '1'),
                token(T_LNUMBER, '2'),
                token(T_LNUMBER, '3')
            ),
            "Unexpected '~' on line 1, expected '+' or '-' or T_LNUMBER(1)."
        );
    }

    function testChainOnHalt() {
        $ts = TokenStream::fromSource("<?php foo { end");

        $this->parseError(
            $ts,
            chain(
                token(T_OPEN_TAG),
                token(T_STRING),
                token('{'),
                token('}')
            ),
            "Unexpected T_STRING(end) on line 1, expected '}'."
        );
    }

    function testChainOnEnd() {
        $ts = TokenStream::fromSource("<?php end");

        $this->parseError(
            $ts,
            chain(
                token(T_OPEN_TAG),
                token(T_STRING),
                token('{'),
                token('}')
            ),
            "Unexpected end at T_STRING(end) on line 1, expected '{'."
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Dead Yay\token() parser at Yay\either(...[2])
     */
    function testEitherDeadParserDetection() {
        either(
            token('.'),
            optional(token('?')),
            token('!') // unreachable
        );
    }

    function testEitherOnError() {
        $ts = TokenStream::fromSource("<?php C");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            either(
                token(T_STRING, 'A'),
                token(T_STRING, 'B')
            ),
            "Unexpected T_STRING(C) on line 1, expected T_STRING(A) or T_STRING(B)."
        );
    }

    function testBetween() {
        $ts = TokenStream::fromSource("<?php {{ x y }} ");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseSuccess(
            $ts,
            between(
                chain(token('{'), token('{')),
                repeat(token(T_STRING)),
                chain(token('}'), token('}'))
            ),
            "T_STRING(x), T_STRING(y)"
        );
    }

    function providerForTestBetweenWithEntryFailure() {
        return [
            ["<?php ~{ x y }}"],
            ["<?php {~ x y }} "]
        ];
    }

    /**
     * @dataProvider providerForTestBetweenWithEntryFailure
     */
    function testBetweenWithEntryFailure(string $src) {
        $ts = TokenStream::fromSource($src);
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            between(
                chain(token('{'), token('{')),
                repeat(token(T_STRING)),
                chain(token('}'), token('}'))
            ),
            "Unexpected '~' on line 1, expected '{'."
        );
    }

    function providerForTestBetweenWithMiddleFailure() {
        return [
            ["<?php {{ ~ x }}"],
            ["<?php {{ x ~ }}"]
        ];
    }

    /**
     * @dataProvider providerForTestBetweenWithMiddleFailure
     */
    function testBetweenWithMiddleFailure(string $src) {
        $ts = TokenStream::fromSource($src);
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseHalt(
            $ts,
            commit(between(
                chain(token('{'), token('{')),
                chain(token(T_STRING), token(T_STRING)),
                chain(token('}'), token('}'))
            )),
            "Unexpected '~' on line 1, expected T_STRING()."
        );
    }

    function providerForTestBetweenWithExitFailure() {
        return [
            ["<?php {{ ~ x }}", "Unexpected '~' on line 1, expected T_STRING()."],
            ["<?php {{ x ~ }}", "Unexpected '~' on line 1, expected '}'."]
        ];
    }

    /**
     * @dataProvider providerForTestBetweenWithExitFailure
     */
    function testBetweenWithExitFailure(string $src, string $msg) {
        $ts = TokenStream::fromSource($src);
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseHalt(
            $ts,
            commit(between(
                chain(token('{'), token('{')),
                repeat(token(T_STRING)),
                chain(token('}'), token('}'))
            )),
            $msg
        );
    }

    function testBetweenWithMiddleErrorWhenMiddleIsOptional() {
        $ts = TokenStream::fromSource("<?php {{ ~ }}");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseHalt(
            $ts,
            commit(between(
                chain(token('{'), token('{')),
                optional(token(T_STRING)),
                chain(token('}'), token('}'))
            )),
            "Unexpected '~' on line 1, expected '}'."
        );
    }

    function testBetweenWithMiddleHalt() {
        $ts = TokenStream::fromSource("<?php {{ x }}");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseHalt(
            $ts,
            commit(between(
                chain(token('{'), token('{')),
                chain(token(T_STRING), token(T_STRING)),
                chain(token('}'), token('}'))
            )),
            "Unexpected '}' on line 1, expected T_STRING()."
        );
    }

    function testEitherWithManyFailures() {
        $ts = TokenStream::fromSource("<?php x ~ y ");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            either(
                chain(token(T_STRING), token(T_LNUMBER)),
                chain(token(T_STRING), token(T_STRING)),
                chain(token(T_STRING), token('~'), token(T_LNUMBER))
            ),
            [
                "Unexpected '~' on line 1, expected T_LNUMBER() or T_STRING().",
                "Unexpected T_STRING(y) on line 1, expected T_LNUMBER()."
            ]
        );
    }

    function testEitherWithManyFailuresII() {
        $ts = TokenStream::fromSource("<?php x ~ ");
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        $this->parseError(
            $ts,
            either(
                chain(token(T_STRING), token(T_LNUMBER)),
                chain(token(T_STRING), token(T_STRING)),
                chain(token(T_STRING), either(token('@'), token(T_STRING)))
            ),
            "Unexpected '~' on line 1, expected T_LNUMBER() or T_STRING() or '@'."
        );
    }

    function providerForTestNamespace() {
        return [
            [
                '<?php \FullQualified\Composed ',
                "T_NS_SEPARATOR(\), T_STRING(FullQualified), T_NS_SEPARATOR(\), T_STRING(Composed)"
            ],
            [
                '<?php \FullQualifiedSimple ',
                "T_NS_SEPARATOR(\), T_STRING(FullQualifiedSimple)"
            ],
            [
                '<?php Relative\Composed ',
                "T_STRING(Relative), T_NS_SEPARATOR(\), T_STRING(Composed)"
            ],
            [
                '<?php RelativeSimple ',
                "T_STRING(RelativeSimple)"
            ],
            [
                '<?php namespace\ExplicitlyRelative ',
                "T_NAMESPACE(namespace), T_NS_SEPARATOR(\), T_STRING(ExplicitlyRelative)"
            ],
        ];
    }

    /**
     * @dataProvider providerForTestNamespace
     */
    function testNamespace(string $src, string $expected) {
        $ts = TokenStream::fromSource($src);
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        $this->parseSuccess($ts, ns(), $expected);
    }

    function providerForTestLs() {
        return [
            [
                '<?php a b c',
                "T_STRING(a)",
                'b'
            ],
            [
                '<?php a, b,',
                "T_STRING(a), T_STRING(b)",
                ','
            ],
            [
                '<?php a, b, c ',
                "T_STRING(a), T_STRING(b), T_STRING(c)",
                ''
            ],
            [
                '<?php a ,b ,c ',
                "T_STRING(a), T_STRING(b), T_STRING(c)",
                ''
            ],
            [
                '<?php a , b , c , ',
                "T_STRING(a), T_STRING(b), T_STRING(c)",
                ','
            ],
            [
                '<?php a , b , c , *',
                "T_STRING(a), T_STRING(b), T_STRING(c)",
                ','
            ],
        ];
    }

    /**
     * @dataProvider providerForTestLs
     */
    function testLs(string $src, string $expected, string $end) {
        $ts = TokenStream::fromSource($src);
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        $this->parseSuccess(
            $ts,
            ls
            (
                token(T_STRING)->as('letter')
                ,
                token(',')
            )
            ,
            $expected
        );

        $this->assertEquals($end, (string) $ts->current());
    }

    function providerForTestLsWithTrailingDelimiter() {
        return [
            [
                '<?php a b c',
                "T_STRING(a)",
                'b'
            ],
            [
                '<?php a, b,',
                "T_STRING(a), T_STRING(b)",
                ''
            ],
            [
                '<?php a, b, c, *',
                "T_STRING(a), T_STRING(b), T_STRING(c)"
            ],
            [
                '<?php a , b , c , *',
                "T_STRING(a), T_STRING(b), T_STRING(c)"
            ],
        ];
    }

    /**
     * @dataProvider providerForTestLsWithTrailingDelimiter
     */
    function testLsWithTrailingDelimiter(string $src, string $expected, string $end = '*') {
        $ts = TokenStream::fromSource($src);
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        $this->parseSuccess(
            $ts,
            lst
            (
                token(T_STRING)->as('letter')
                ,
                token(',')
            )
            ,
            $expected
        );

        $this->assertEquals($end, (string) $ts->current());
    }

    function providerForTestLsWithTrailingDelimiterKeepingTheDelimiter() {
        return [
            [
                '<?php a b, c',
                "T_STRING(a)",
                'b'
            ],
            [
                '<?php a b c',
                "T_STRING(a)",
                'b'
            ],
            [
                '<?php a, b c',
                "T_STRING(a), ',', T_STRING(b)",
                'c'
            ],
            [
                '<?php a, b, c',
                "T_STRING(a), ',', T_STRING(b), ',', T_STRING(c)",
                ''
            ],
            [
                '<?php a, b, c,',
                "T_STRING(a), ',', T_STRING(b), ',', T_STRING(c), ','",
                ''
            ],
            [
                '<?php a, b, c, *',
                "T_STRING(a), ',', T_STRING(b), ',', T_STRING(c), ','",
                '*'
            ],
            [
                '<?php a , b , c , *',
                "T_STRING(a), ',', T_STRING(b), ',', T_STRING(c), ','",
                '*'
            ],
            [
                '<?php a , b , c , *,',
                "T_STRING(a), ',', T_STRING(b), ',', T_STRING(c), ','",
                '*'
            ],
        ];
    }

    /**
     * @dataProvider providerForTestLsWithTrailingDelimiterKeepingTheDelimiter
     */
    function testLsWithTrailingDelimiterKeepingTheDelimiter(string $src, string $expected, string $end) {
        $ts = TokenStream::fromSource($src);
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        $this->parseSuccess(
            $ts,
            lst
            (
                token(T_STRING)->as('letter')
                ,
                token(',')
                ,
                LS_KEEP_DELIMITER
            )
            ,
            $expected
        );

        $this->assertEquals($end, (string) $ts->current());
    }

    function testConsume() {
        $ts = TokenStream::fromSource('<?php A  {X} B {X} C    {x} ');
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");

        traverse
        (
            consume
            (
                chain(
                    token('{')
                    ,
                    token(T_STRING)
                    ,
                    token('}')
                )
            )
        )
        ->parse($ts);

        $this->assertEquals('<?php A   B  C     ', (string) $ts);

        $ts = TokenStream::fromSource('<?php A  {-}   B    {-}     ');
        $this->parseSuccess($ts, token(T_OPEN_TAG), "T_OPEN_TAG(<?php )");
        traverse
        (
            consume
            (
                chain(
                    token('{')
                    ,
                    token('-')
                    ,
                    token('}')
                )
                ,
                CONSUME_DO_TRIM
            )
        )
        ->parse($ts);

        $this->assertEquals('<?php A  B    ', (string) $ts);
    }


    function goodExpressionDataProvider()
    {
        foreach($this->getFixture('expression/good') as $i => $src) yield "Expression {$i}" => [$src];
    }


    function randomExpressionDataProvider()
    {
        foreach($this->getFixture('expression/random') as $i => $src) yield "Expression {$i}" => [$src];
    }


    function evalExpressionDataProvider()
    {
        foreach($this->getFixture('expression/eval') as $i => $src) yield "Expression {$i}" => [$src, true];
    }
    /**
     * @dataProvider goodExpressionDataProvider
     * @dataProvider randomExpressionDataProvider
     * @dataProvider evalExpressionDataProvider
     */
    function testExpressionParserWithGoodExpressions(string $src, bool $eval = false)
    {
        $ts = TokenStream::fromSourceWithoutOpenTag($src);
        $ast = $this->parseSuccess($ts, expression());
        $this->assertInstanceOf(NodeEnd::class, $ts->index());

        if ($eval) {
            $this->assertTrue(
                $this->eval($src) === $this->eval($this->printExpressionAst($ast))
            )
            ;
        }
    }

    function badExpressionDataProvider()
    {
        foreach($this->getFixture('expression/bad') as $i => $src) yield "Expression {$i}" => [$src];
    }

    /**
     * @dataProvider badExpressionDataProvider
     *
     * The token stream can not be poiting to it's NodeEnd after parsing
     */
    function testExpressionParserWithBadExpressions($src)
    {
        $ts = TokenStream::fromSourceWithoutOpenTag($src);
        expression()->withErrorLevel(Error::ENABLED)->parse($ts);
        $this->assertNotInstanceOf(NodeEnd::class, $ts->index());
    }

    function testRaytrace() {
        $this->assertRaytrace("T_STRING()", token(T_STRING));
        $this->assertRaytrace("ANY()", any());
        $this->assertRaytrace("';'", optional(token(';')));
        $this->assertRaytrace("'?'", repeat(token('?')));
        $this->assertRaytrace("'!'", chain(token('!'), token('.')));
        $this->assertRaytrace("'!' | '.'", chain(optional(token('!')), token('.')));
        $this->assertRaytrace("'!' | '.'", either(token('!'), token('.')));
        $this->assertRaytrace("'{'", between(token('{'), token(T_STRING), token('}')));
        $this->assertRaytrace("'{' | T_STRING()", between(optional(token('{')), token(T_STRING), token('}')));

        // repeat() with optional() leading parser
        $this->assertRaytrace(
            "'!' | '@'",
            repeat(
                chain(
                    optional(token('!')),
                    token('@'),
                    token('*')
                )
            )
        );

        // chain() with optional(either())
        $this->assertRaytrace(
            "'@' | '$' | '!'",
            chain(
                optional(
                    either(
                        token('@'),
                        token('$')
                    )
                ),
                token('!'),
                token('*')
            )
        );

        // extreme nesting I
        $this->assertRaytrace(
            "':' | '.' | '-' | '~' | T_STRING(foo) | '@' | '!' | '(' | T_STRING() | ANY()",
            either(
                chain(
                    optional(token(':')),
                    token('.'),
                    token('!')
                ),
                repeat(
                    chain(
                        optional(token('-')),
                        chain(
                            either(
                                token('~'),
                                token(T_STRING, 'foo')
                            ),
                            token(T_STRING, 'bar')
                        )
                    )
                ),
                either(
                    token('@'),
                    chain(
                        token('!'),
                        token(T_STRING)
                    ),
                    between(
                        optional(token('(')),
                        token(T_STRING),
                        token(')')
                    )
                ),
                any()
            )
        );

        // extreme nesting II
        $this->assertRaytrace(
            "':' | '~' | T_STRING(foo) | T_STRING(bar) | '@' | '!' | '(' | ANY()",
            either(
                repeat(
                    chain(
                        token(':'),
                        token('.')
                    )
                ),
                chain(
                    repeat(
                        either(
                            token('~'),
                            token(T_STRING, 'foo'),
                            token(T_STRING, 'bar')
                        )
                    ),
                    token(T_STRING, 'baz')
                ),
                either(
                    token('@'),
                    chain(
                        token('!'),
                        token(T_STRING)
                    )
                    ,
                    between(
                        token('('),
                        either(
                            token('='),
                            token('+')
                        ),
                        token(')')
                    )
                )
                ,
                repeat(
                    any()
                )
            )
        );
    }

    function testBetweenAst()
    {
        $ts = TokenStream::fromSource('<?php list a b c;');
        $ast =
            chain
            (
                token(T_OPEN_TAG)
                ,
                between
                (
                    token(T_LIST)
                    ,
                    repeat
                    (
                        token(T_STRING)
                    )
                    ->as('list')
                    ,
                    token(';')
                )
                ->as('list')
            )
            ->parse($ts);

        $this->assertEquals("T_STRING(a)", $ast->{'list 0'}->dump());
        $this->assertEquals("T_STRING(b)", $ast->{'list 1'}->dump());
        $this->assertEquals("T_STRING(c)", $ast->{'list 2'}->dump());
    }

    function testBracesAst()
    {
        $ts = TokenStream::fromSource('<?php { a; b; c;  }');
        $ast =
            chain(
                token(T_OPEN_TAG)
                ,
                braces()->as('block')
            )
            ->parse($ts);

        $this->assertEquals("T_STRING(a)", $ast->{'block 0'}->dump());
        $this->assertEquals("';'", $ast->{'block 1'}->dump());
        $this->assertEquals("T_WHITESPACE( )", $ast->{'block 2'}->dump());
        $this->assertEquals("T_STRING(b)", $ast->{'block 3'}->dump());
        $this->assertEquals("';'", $ast->{'block 4'}->dump());
        $this->assertEquals("T_WHITESPACE( )", $ast->{'block 5'}->dump());
        $this->assertEquals("T_STRING(c)", $ast->{'block 6'}->dump());
        $this->assertEquals("';'", $ast->{'block 7'}->dump());
        $this->assertEquals("T_WHITESPACE(  )", $ast->{'block 8'}->dump());
    }

    function testastOptionalAst()
    {
        $ast =
            chain(
                token(T_OPEN_TAG)
                ,
                optional(token(T_STRING))->as('name')
            )
            ->as('source')
            ->parse(TokenStream::fromSource('<?php // end'));

        $this->assertEmpty($ast->{'name'});
    }

    function testastNestedAst()
    {
        $ts = TokenStream::fromSource('<?php
            interface Foo
            {
                public abstract function foo();
                public abstract static function bar();
                function baz();
            }
        ');

        $modifier = either(token(T_PUBLIC), token(T_STATIC), token(T_PRIVATE));

        $modifiers = optional(
            either(
                chain($modifier, $modifier)
                ,
                $modifier
            )
        );

        $ast = chain
        (
            token(T_OPEN_TAG)
            ,
            chain
            (
                token(T_INTERFACE)
                ,
                token(T_STRING)->as('name')
                ,
                token('{')
                ,
                optional
                (
                    repeat
                    (
                        chain
                        (
                            optional
                            (
                                either
                                (
                                    set
                                    (
                                        either
                                        (
                                            token(T_PUBLIC)->as('public')
                                            ,
                                            token(T_STATIC)->as('static')
                                            ,
                                            token(T_ABSTRACT)->as('abstract')
                                        )
                                    )
                                    ,
                                    always // if no modifier assume public
                                    (
                                        new Token(T_PUBLIC, 'public')
                                    )
                                    ->as('public')
                                )
                            )
                            ->as('is')
                            ,
                            token(T_FUNCTION)
                            ,
                            token(T_STRING)->as('name')
                            ,
                            token('(')
                            ,
                            token(')')
                            ,
                            token(';')
                        )
                    )
                )
                ->as('methods')
                ,
                token('}')
            )
            ->as('interface')
        )
        ->parse($ts);

        $this->assertEquals('Foo', (string) $ast->{'interface name'});

        $this->assertEquals('foo', (string) $ast->{'interface methods 0 name'});
        $this->assertEquals('public', (string) $ast->{'interface methods 0 is public'});
        $this->assertEquals('abstract', (string) $ast->{'interface methods 0 is abstract'});
        $this->assertEmpty($ast->{'interface methods 0 is static'});

        $this->assertEquals('bar', (string) $ast->{'interface methods 1 name'});
        $this->assertEquals('public', (string) $ast->{'interface methods 1 is public'});
        $this->assertEquals('abstract', (string) $ast->{'interface methods 1 is abstract'});
        $this->assertEquals('static', (string) $ast->{'interface methods 1 is static'});

        $this->assertEquals('baz', (string) $ast->{'interface methods 2 name'});
        $this->assertEquals('public', (string) $ast->{'interface methods 2 is public'});
        $this->assertNull($ast->{'interface methods 2 is abstract'});
        $this->assertNull($ast->{'interface methods 2 is static'});
    }

    private function getFixture(string $label) : array
    {
        return include __DIR__ . "/fixtures/{$label}.php";
    }

    private function printExpressionAst(Ast $ast){
        $buff = '';

        if ($ast->operator) {
            switch ($ast->operator->meta()->get('arity')) {
                case ExpressionParser::ARITY_BINARY:
                    $buff .= '(';
                    $buff .= $this->printExpressionAst($ast->left);
                    $buff .= ' ' . $this->printExpressionAst($ast->operator) . ' ';
                    $buff.= $this->printExpressionAst($ast->right);
                    $buff .= ')';
                    break;
                case ExpressionParser::ARITY_TERNARY:
                    $buff .= '(';
                    $buff .= $this->printExpressionAst($ast->left);
                    $buff .= ' ' . $this->printExpressionAst($ast->middle) . ' ';
                    $buff.= $this->printExpressionAst($ast->right);
                    $buff .= ')';
                    break;
                case ExpressionParser::ARITY_UNARY:
                    switch ($ast->operator->meta()->get('associativity')) {
                        case ExpressionParser::ASSOC_NONE:
                        case ExpressionParser::ASSOC_LEFT:
                            $buff .= $this->printExpressionAst($ast->left);
                            $buff .= ' ' . $this->printExpressionAst($ast->operator) . ' ';
                            break;
                        case ExpressionParser::ASSOC_RIGHT:
                            $buff .= ' ' . $this->printExpressionAst($ast->operator) . ' ';
                            $buff.= $this->printExpressionAst($ast->right);
                            break;
                        default:
                            throw new \Exception('Unknown operator associativity.');
                    }
                    break;
                default:
                    throw new \Exception('Unknown operator arity.');
            }
        }
        else $buff .= implode('', $ast->tokens());

        return $buff;
    }

    private function eval(string $src)
    {
        ob_start();
        $result = eval("return {$src};");
        ob_end_clean();

        return $result;
    }
}
