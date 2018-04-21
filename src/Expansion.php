<?php declare(strict_types=1);

namespace Yay;

use function Yay\DSL\Expanders\{ hygienize };

class Expansion extends MacroMember {

    const
        E_BAD_EXPANSION = "Bad macro expansion identifier '%s' on line %d.",
        E_BAD_EXPANDER = "Bad macro expander '%s' on line %d.",
        E_EMPTY_EXPANDER_SLICE = "Empty expander slice on '%s()' at line %d.",
        E_UNDEFINED_EXPANSION = "Undefined macro expansion '%s' on line %d with context: %s"
    ;

    private
        $expansion,
        $constant = true,
        $unsafe = false,
        $cloaked = false,
        $recursive = false
    ;

    function __construct(array $expansion, Map $tags, Map $scope) {
        $this->expansion = $this->compile($expansion, $scope);
        if ($tags->contains('unsafe')) $this->unsafe = false;
        $this->recursive = $tags->contains('recursion');
    }

    function isRecursive() : bool {
        return $this->recursive;
    }

    function isEmpty() : bool {
        return $this->expansion->isEmpty();
    }

    function expand(Ast $crossover, Engine $engine) : TokenStream {
            $expansion = clone $this->expansion;

            if ($this->unsafe) hygienize($expansion, $engine);

            if ($this->constant) return $expansion;

            return $this->mutate($expansion, $crossover, $engine);
    }

    private function compile(array $expansion, Map $context) : TokenStream {
        $cg = (object) [
            'ts' => TokenStream::fromSlice($expansion),
            'context' => $context
        ];

        $cg->ts->trim();

        /*
           Here we analyze the expansion looking for "unsafe" productions like:

            - variables
            - goto labels
            - goto instructions

           These will be escaped later if the macro is considered unsafe and does NOT
           contain the explicit `:unsafe` tag as in:

           ```
           $(macro :unsafe) {
               # the pattern
           } >> {
               # the expansion
           }
           ```
        */
        traverse
        (
            either
            (
                token(T_VARIABLE)
                ,
                chain(identifier(), token(':'))
                ,
                chain(token(T_GOTO), identifier())
            )
            ->onCommit(function() {
                $this->unsafe = true;
            })
        )
        ->parse($cg->ts);

        $cg->ts->reset();

        traverse
        (
            /*
                Matches `\\$(...)` and `\\$$(...)` escape syntax, as in:

                    \\$(something to be ignored by the preprocessor)

                Compiles to:

                    Token(Token::ESCAPED, '$(something to be ignored by the preprocessor)')

                This enables anyone to use the reserved sigil `$()` as part of an expansion
                by escapting it as `\\$()`
             */
            consume
            (
                chain
                (
                    either(buffer('\\\\$('), buffer('\\\\$$('))->as('declaration')
                    ,
                    layer()
                    ,
                    token(')')
                )
            )
            ->onCommit(function(Ast $result) use ($cg){
                $cg->ts->inject(
                    TokenStream::fromSequence(
                        new Token(
                            Token::ESCAPED,
                            $result->implode(),
                            $result->{'* declaration'}->tokens()[0]->line()
                        )
                    )
                );
                $this->cloaked = true;
                $this->constant = false;
            })
            ,
            // skips the escape token produced by the rule above ^
            token(Token::ESCAPED)
            ,
            /*
                Here we analyze the expansion and mark the expansion as constant or not.
                Constant means the expansion is not variable. An expansion is considered not
                constant when it contains one of the following constructs:

                - $(name)
                - $(name ? {...}) // if not empty, expand
                - $(name ! {...}) // if empty, expand
                - $(name ?! {...}) // if empty, expand
                - $(name... ? {...}) // if present, expand

             */
            either
            (
                $this->sigil(
                    label()->as('label')
                    ,
                    token('?')
                    ,
                    token('!')
                    ,
                    braces()->as('expansion')
                )
                ,
                $this->sigil(
                    label()->as('label')
                    ,
                    token('?')
                    ,
                    braces()->as('expansion')
                )
                ,
                $this->sigil(
                    label()->as('label')
                    ,
                    token('!')
                    ,
                    braces()->as('expansion')
                )
                ,
                chain(
                    buffer('$$')->as('declaration')
                    ,
                    token('(')
                    ,
                    ns()->as('expander')
                    ,
                    either(parentheses(), braces())->as('args')
                    ,
                    commit(token(')'))
                )
                ,
                $this->sigil(
                    label()->as('label')
                    ,
                    optional(token('?'))->as('optional')
                    ,
                    token(T_ELLIPSIS)
                    ,
                    optional
                    (
                        parentheses()->as('delimiters')
                    )
                    ,
                    braces()->as('expansion')
                )
                ->onCommit(function(Ast $result) use($cg) {
                    if (! $result->optional)
                        $this->lookupScope($result->label, $cg->context, self::E_UNDEFINED_EXPANSION);
                })
                ,
                $this->sigil(label()->as('label'))
                    ->onCommit(function(Ast $result) use($cg) {
                        $this->lookupScope($result->{'* label'}->token(), $cg->context, self::E_UNDEFINED_EXPANSION);
                    })
            )
            ->onCommit(function(Ast $result) {
                $this->constant = false;
            })
        )
        ->parse($cg->ts);

        $cg->ts->reset();

        return $cg->ts;
    }

    private function mutate(TokenStream $ts, Ast $context, Engine $engine) : TokenStream {

        static $states, $parser;

        $states = $states ?? new Stack;

        $parser =
            $parser ??
            traverse
            (
                token(Token::ESCAPED)
                ,
                consume
                (
                    $this->sigil(
                        label()->as('label')
                        ,
                        token('?')
                        ,
                        token('!')
                        ,
                        braces()->as('expansion')
                    )
                )
                ->onCommit(function(Ast $result)  use($states) {
                    $cg = $states->current();

                    $tokens = $cg->this->lookupAstOptional($result->{'label'}, $cg->context)->tokens();

                    $expansion = TokenStream::fromSlice($tokens ?: $result->{'expansion'});

                    $mutation = $cg->this->mutate(
                        $expansion,
                        $cg->context,
                        $cg->engine
                    );

                    $cg->ts->inject($mutation);
                })
                ,
                consume
                (
                    $this->sigil(
                        label()->as('label')
                        ,
                        token('?')
                        ,
                        braces()->as('expansion')
                    )
                )
                ->onCommit(function(Ast $result)  use($states) {
                    $cg = $states->current();

                    $context = $cg->this->lookupAstOptional($result->{'label'}, $cg->context);

                    if ($context->isEmpty()) return;

                    $expansion = TokenStream::fromSlice($result->{'expansion'});

                    $mutation = $cg->this->mutate(
                        $expansion,
                        $cg->context,
                        $cg->engine
                    );

                    $cg->ts->inject($mutation);
                })
                ,
                consume
                (
                    $this->sigil(
                        label()->as('label')
                        ,
                        token('!')
                        ,
                        braces()->as('expansion')
                    )
                )
                ->onCommit(function(Ast $result)  use($states) {
                    $cg = $states->current();

                    $context = $cg->this->lookupAstOptional($result->{'label'}, $cg->context);

                    if (! $context->isEmpty()) return;

                    $expansion = TokenStream::fromSlice($result->{'expansion'});

                    $mutation = $cg->this->mutate(
                        $expansion,
                        $cg->context,
                        $cg->engine
                    );

                    $cg->ts->inject($mutation);
                })
                ,
                consume
                (
                    chain(
                        buffer('$$')->as('declaration')
                        ,
                        token('(')
                        ,
                        ns()->as('expander')
                        ,
                        either(parentheses(), braces())->as('args')
                        ,
                        commit(token(')'))
                    )
                )
                ->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();

                    $expander = $result->{'* expander'};
                    if (\count($result->{'args'}) === 0)
                        $cg->this->fail(self::E_EMPTY_EXPANDER_SLICE, $expander->implode(), $expander->tokens()[0]->line());

                    $expansion = TokenStream::fromSlice($result->{'args'});
                    $mutation = $cg->this->mutate($expansion, $cg->context, $cg->engine);

                    $mutation = $cg->this->lookupExpander($expander)($mutation, $cg->engine);
                    $cg->ts->inject($mutation);
                })
                ,
                consume
                (
                    $this->sigil(
                        label()->as('label')
                        ,
                        optional(token('?'))->as('optional')
                        ,
                        token(T_ELLIPSIS)
                        ,
                        optional
                        (
                            parentheses()->as('delimiters')
                        )
                        ,
                        braces()->as('expansion')
                    )
                )
                ->onCommit(function(Ast $result)  use($states) {
                    $cg = $states->current();

                    if ($result->optional)
                        $context = $cg->this->lookupAstOptional($result->{'label'}, $cg->context)->unwrap();
                    else
                        $context = $cg->this->lookupAst($result->{'label'}, $cg->context, self::E_UNDEFINED_EXPANSION)->unwrap();

                    if (null === $context) return;

                    $delimiters = $result->{'delimiters'};

                    // normalize associative arrays
                    if (array_values($context) !== $context) $context = [$context];

                    foreach (array_reverse($context) as $i => $subContext) {
                        $expansion = TokenStream::fromSlice($result->{'expansion'});
                        $mutation = $cg->this->mutate(
                            $expansion,
                            (new Ast('', $subContext))->withParent($cg->context),
                            $cg->engine
                        );
                        if ($i !== 0) foreach ($delimiters as $d) $mutation->push($d);
                        $cg->ts->inject($mutation);
                    }
                })
                ,
                consume
                (
                    $this->sigil(label()->as('label'))
                )
                ->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();
                    $label = $result->{'* label'}->token();
                    $tokens = $cg->this->lookupAst($label, $cg->context, self::E_UNDEFINED_EXPANSION)->tokens();
                    $cg->ts->inject(TokenStream::fromSlice($tokens));
                })
            )
        ;

        $cg = (object) [
            'ts' => $ts,
            'context' => $context->label() ? new Ast('', [$context->label() => $context->unwrap()]) : $context,
            'engine' => $engine,
            'this' => $this,
        ];

        $states->push($cg);

        $parser->parse($cg->ts);

        $states->pop();

        $cg->ts->reset();

        if ($this->cloaked) {
            traverse
            (
                consume
                (
                    token(Token::ESCAPED)
                )
                ->onCommit(function(Ast $result) use($cg) {
                    $cg->ts->inject(
                        TokenStream::fromSourceWithoutOpenTag(
                            ltrim((string) $result->token(), '\\')
                        )
                    );
                })
            )
            ->parse($cg->ts);

            $cg->ts->reset();
        }

        return $cg->ts;
    }

    private function lookupExpander(Ast $expander) : string {
        $resolvedName = $name = $expander->implode();

        if (! $expander->{'full-qualified'}) $resolvedName = '\Yay\Dsl\Expanders\\' . $name;

        if (! function_exists($resolvedName))
            $this->fail(self::E_BAD_EXPANDER, $name, $expander->tokens()[0]->line());

        return $resolvedName;
    }

    private function lookupScope(Token $token, Map $context, string $error) : bool {
        $symbol = (string) $token;
        if (! ($result = $context->get($symbol))) {
            $this->fail(
                $error,
                $symbol,
                $token->line(),
                json_encode(
                    $context->symbols(),
                    self::PRETTY_PRINT
                )
            );
        }

        return $result;
    }

    private function lookupAst(Token $token, Ast $context, string $error) : Ast {
        $symbol = (string) $token;
        if (null === ($result = $context->get('* ' . $symbol))->unwrap()) {
            $this->fail(
                $error,
                $symbol,
                $token->line(),
                json_encode(
                    $context->symbols(),
                    self::PRETTY_PRINT
                )
            );
        }

        return $result;
    }

    private function lookupAstOptional(Token $token, Ast $context) : Ast {
        $result = $context->get('* ' . (string) $token);

        return $result;
    }
}
