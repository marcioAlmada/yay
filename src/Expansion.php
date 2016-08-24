<?php declare(strict_types=1);

namespace Yay;

use function Yay\DSL\Expanders\{ hygienize };

class Expansion extends MacroMember {

    const
        PRETTY_PRINT =
                JSON_PRETTY_PRINT
            |   JSON_BIGINT_AS_STRING
            |   JSON_UNESCAPED_UNICODE
            |   JSON_UNESCAPED_SLASHES
    ;

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
        $this->unsafe = (bool) ($this->unsafe ^ $tags->contains('·unsafe'));
        $this->recursive = $tags->contains('·recursion');
    }

    function isRecursive() : bool {
        return $this->recursive;
    }

    function isEmpty() : bool {
        return $this->expansion->isEmpty();
    }

    function expand(Ast $crossover, Cycle $cycle, Directives $directives, BlueContext $blueContext) : TokenStream {
            $expansion = clone $this->expansion;

            if ($this->unsafe)
                hygienize($expansion, ['scope' => $cycle->id(),]);

            return $this->mutate($expansion, $crossover, $cycle, $directives, $blueContext);
    }

    private function compile(array $expansion, Map $context) : TokenStream {
        $cg = (object) [
            'ts' => TokenStream::fromSlice($expansion),
            'context' => $context
        ];

        $cg->ts->trim();

        traverse
        (
            consume
            (
                chain
                (
                    token(T_NS_SEPARATOR)
                    ,
                    token(T_NS_SEPARATOR)
                    ,
                    parentheses()->as('cloaked')
                )
            )
            ->onCommit(function(Ast $result) use ($cg){
                $cg->ts->inject(
                    TokenStream::fromSequence(
                        new Token(
                            Token::CLOAKED,
                            implode('', $result->cloaked)
                        )
                    )
                );
                $this->cloaked = true;
            })
            ,
            token(Token::CLOAKED)
            ,
            either
            (
                token(T_VARIABLE)
                ,
                chain(identifier(), token(':'))
                ,
                chain(token(T_GOTO), identifier())
            )
            ->onCommit(function() { $this->unsafe = true; })
            ,
            chain
            (
                rtoken('/^··\w+$/')->as('expander')
                ,
                parentheses()->as('args')
            )
            ->onCommit(function(){
                $this->constant = false;
            })
            ,
            chain
            (
                rtoken('/^·\w+|···\w+$/')->as('label')
                ,
                operator('···')
                ,
                optional
                (
                    parentheses()->as('delimiters')
                )
                ,
                braces()->as('expansion')
            )
            ->onCommit(function(Ast $result) use($cg) {
                $this->lookupContext($result->label, $cg->context, self::E_UNDEFINED_EXPANSION);
                $this->constant = false;
            })
            ,
            rtoken('/^(T_\w+·\w+|·\w+|···\w+)$/')
                ->onCommit(function(Ast $result) use($cg) {
                    $this->lookupContext($result->token(), $cg->context, self::E_UNDEFINED_EXPANSION);
                    $this->constant = false;
                })
            ,
            rtoken('/·/')
                ->onCommit(function(Ast $result) use($cg) {
                    $this->lookupContext($result->token(), $cg->context, self::E_BAD_EXPANSION);
                })
        )
        ->parse($cg->ts);

        $cg->ts->reset();

        return $cg->ts;
    }

    private function mutate(TokenStream $ts, Ast $context, Cycle $cycle, Directives $directives, BlueContext $blueContext) : TokenStream {

        if ($this->constant) return $ts;

        static $states, $parser;

        $states = $states ?? new Stack;

        $parser =
            $parser ??
            traverse
            (
                token(Token::CLOAKED)
                ,
                consume
                (
                    chain
                    (
                        rtoken('/^··\w+$/')->as('expander')
                        ,
                        parentheses()->as('args')
                    )
                )
                ->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();

                    $expander = $result->expander;
                    if (\count($result->args) === 0)
                        $cg->this->fail(self::E_EMPTY_EXPANDER_SLICE, (string) $expander, $expander->line());

                    $context = Map::fromKeysAndValues([
                        'scope' => $cg->cycle->id(),
                        'directives' => $cg->directives,
                        'blueContext' => $cg->blueContext
                    ]);
                    $expansion = TokenStream::fromSlice($result->args);
                    $mutation = $cg->this->mutate(clone $expansion, $cg->context, $cg->cycle, $cg->directives, $cg->blueContext);
                    $mutation = $cg->this->lookupExpander($expander)($mutation, $context);
                    $cg->ts->inject($mutation);
                })
                ,
                consume
                (
                    chain
                    (
                        rtoken('/^·\w+|···\w+$/')->as('label')
                        ,
                        operator('···')
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

                    $context = $cg->this->lookupContext($result->label, $cg->context, self::E_UNDEFINED_EXPANSION);

                    $expansion = TokenStream::fromSlice($result->expansion);
                    $delimiters = $result->delimiters;

                    // normalize single context
                    if (array_values($context) !== $context) $context = [$context];

                    foreach (array_reverse($context) as $i => $subContext) {
                        $mutation = $cg->this->mutate(
                            clone $expansion,
                            (new Ast(null, $subContext))->withParent($cg->context),
                            $cg->cycle,
                            $cg->directives,
                            $cg->blueContext
                        );
                        if ($i !== 0) foreach ($delimiters as $d) $mutation->push($d);
                        $cg->ts->inject($mutation);
                    }
                })
                ,
                consume
                (
                    rtoken('/^(T_\w+·\w+|·\w+|···\w+)$/')->as('label')
                )
                ->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();

                    $context = $cg->this->lookupContext($result->label, $cg->context, self::E_UNDEFINED_EXPANSION);

                    if ($context instanceof Token) {
                        $cg->ts->inject(TokenStream::fromSequence($context));
                    }
                    elseif (is_array($context) && \count($context)) {
                        $tokens = [];
                        array_walk_recursive(
                            $context,
                            function(Token $token) use(&$tokens) {
                                $tokens[] = $token;
                            }
                        );
                        $cg->ts->inject(TokenStream::fromSlice($tokens));
                    }
                })
            )
        ;

        $cg = (object) [
            'ts' => $ts,
            'context' => $context,
            'directives' => $directives,
            'cycle' => $cycle,
            'this' => $this,
            'blueContext' => $blueContext
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
                    token(Token::CLOAKED)
                )
                ->onCommit(function(Ast $result) use($cg) {
                    $cg->ts->inject(
                        TokenStream::fromSourceWithoutOpenTag(
                            (string) $result->token()
                        )
                    );
                })
            )
            ->parse($cg->ts);

            $cg->ts->reset();
        }

        return $cg->ts;
    }


    private function lookupExpander(Token $token) : string {
        $identifier = (string) $token;
        $expander = '\Yay\Dsl\Expanders\\' . explode('··', $identifier)[1];

        if (! function_exists($expander))
            $this->fail(self::E_BAD_EXPANDER, $identifier, $token->line());

        return $expander;
    }

    private function lookupContext(Token $token, Context $context, string $error) /*: Token | []Token*/ {
        $symbol = (string) $token;
        if (null === ($result = $context->get($symbol))) {
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
}
