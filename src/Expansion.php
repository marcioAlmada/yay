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

    function __construct(array $expansion, Map $tags) {
        $this->expansion = $this->compile($expansion);
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

    private function compile(array $expansion) : TokenStream {
        $cg = (object) [
            'ts' => TokenStream::fromSlice($expansion),
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
                    either(escaped_sigil_prefix(), escaped_expander_sigil_prefix())->as('declaration')
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
                - $(name ?! {...}) // try name or fallback {...}
                - $(name... {...}) // expand as a list
                - $(name... ? {...}) // if defined, expand as a list
             */
            either
            (
                $this->conditionalLabelExpansion()
                ,
                $this->expanderExpansion()
                ,
                $this->astEllipsisExpansion()
                ,
                $this->labelExpansion()
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
                consume($this->conditionalLabelExpansion())->onCommit(function(Ast $result)  use($states) {

                    $cg = $states->current();

                    switch ($result->{'* condition-type'}->list()->current()->label()) {
                        case 'node-coalesce':
                            $tokens = $cg->this->lookupAstOptional($result->{'* label'}, $cg->context)->tokens();
                            $expansion = TokenStream::fromSlice($tokens ?: $result->{'expansion'});
                        break;
                        case 'if-node-is-not-empty':
                            if ($cg->this->lookupAstOptional($result->{'* label'}, $cg->context)->isEmpty()) return;
                            $expansion = TokenStream::fromSlice($result->{'expansion'});
                        break;
                        case 'if-node-is-empty':
                            if (! $cg->this->lookupAstOptional($result->{'* label'}, $cg->context)->isEmpty()) return;
                            $expansion = TokenStream::fromSlice($result->{'expansion'});
                        break;
                    }

                    $mutation = $cg->this->mutate(
                        $expansion,
                        $cg->context,
                        $cg->engine
                    );

                    $cg->ts->inject($mutation);
                })
                ,
                consume($this->expanderExpansion())->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();

                    $expander = $result->{'* expander'};
                    if (\count($result->{'args'}) === 0)
                        $cg->this->fail(self::E_EMPTY_EXPANDER_SLICE, $expander->implode(), $expander->tokens()[0]->line());

                    $expansion = TokenStream::fromSlice($result->{'args'});
                    $mutation = $cg->this->mutate($expansion, $cg->context, $cg->engine);

                    $expander = $cg->this->compileCallable('\Yay\Dsl\Expanders\\', $expander, self::E_BAD_EXPANDER);
                    $mutation = $expander($mutation, $cg->engine);
                    $cg->ts->inject($mutation);
                })
                ,
                consume($this->astEllipsisExpansion())->onCommit(function(Ast $result)  use($states) {
                    $cg = $states->current();

                    if ($result->optional)
                        $context = $cg->this->lookupAstOptional($result->{'* label'}, $cg->context);
                    else
                        $context = $cg->this->lookupAst($result->{'* label'}, $cg->context, self::E_UNDEFINED_EXPANSION);

                    if ($context->isEmpty()) return;

                    $context = $context->unwrap();

                    if (! is_array($context))
                        $this->fail(
                            "Error unpacking a non unpackable Ast node on `$(%s%s... {` at line %d with context: %s\n\n%s",
                            $result->{'* label name'}->token(),
                            $result->optional,
                            $result->{'* label name'}->token()->line(),
                            json_encode([$context], self::PRETTY_PRINT),
                            sprintf("Hint: use a non ellipsis expansion as in `$(%s %s {`", $result->{'* label name'}->token(), $result->optional)
                        );

                    $delimiters = $result->{'delimiters'};

                    // normalize associative arrays
                    if (array_values($context) !== $context) $context = [$context];

                    foreach (array_reverse($context, true) as $i => $scope) {
                        if ($key = $result->{'key'}) {
                            $scope[(string) $result->{'key'}] = new Token(T_LNUMBER, (string) $i);
                        }
                        $expansion = TokenStream::fromSlice($result->{'expansion'});
                        $mutation = $cg->this->mutate(
                            $expansion,
                            (new Ast('', $cg->context->unwrap() + (is_array($scope) ? $scope : [$scope]))),
                            $cg->engine
                        );
                        if ($i !== count($context)-1) foreach ($delimiters as $d) $mutation->push($d);
                        $cg->ts->inject($mutation);
                    }
                })
                ,
                consume($this->labelExpansion())->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();
                    $tokens = $cg->this->lookupAst($result->{'* label'}, $cg->context, self::E_UNDEFINED_EXPANSION)->tokens();
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

    private function lookupAst(Ast $label, Ast $context, string $error) : Ast {
        $symbol = $label->{'* name'}->token();
        if (null === ($result = $context->get('* ' . $symbol))->unwrap()) {
            $this->fail(
                $error,
                $label->{'complex'} ? $label->{'* complex_name'}->implode() : $symbol,
                $symbol->line(),
                json_encode(
                    $label->{'complex'}
                        ? array_values(array_filter($context->symbols(), 'is_string'))
                        : $context->symbols()
                    ,
                    self::PRETTY_PRINT
                )
            );
        }

        return $result;
    }

    private function lookupAstOptional(Ast $label, Ast $context) : Ast {
        $result = $context->get('* ' . (string) $label->{'* name'}->token());

        return $result;
    }

    private function conditionalLabelExpansion() : Parser {
        return
            sigil
            (
                label_or_array_access()->as('label')
                ,
                node
                (
                    either
                    (
                        chain(token('?'), token('!'))->as('node-coalesce')
                        ,
                        token('?')->as('if-node-is-not-empty')
                        ,
                        token('!')->as('if-node-is-empty')
                    )
                )
                ->as('condition-type')
                ,
                braces()->as('expansion')
            )
        ;
    }

    private function expanderExpansion() : Parser {
        return
            expander_sigil
            (
                ns()->as('expander')
                ,
                either(parentheses(), braces())->as('args')
            )
        ;
    }

    private function astEllipsisExpansion() : Parser {
        return
            sigil
            (
                label_or_array_access()->as('label')
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
                optional
                (
                    label()->as('key')
                )
                ,
                braces()->as('expansion')
            )
        ;
    }

    private function labelExpansion() : Parser {
        return sigil(label_or_array_access());
    }
}
