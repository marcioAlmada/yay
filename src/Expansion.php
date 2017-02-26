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
        if ($tags->contains('·unsafe')) $this->unsafe = false;
        $this->recursive = $tags->contains('·recursion');
    }

    function isRecursive() : bool {
        return $this->recursive;
    }

    function isEmpty() : bool {
        return $this->expansion->isEmpty();
    }

    function expand(Ast $crossover, Engine $engine) : TokenStream {
            $expansion = clone $this->expansion;

            if ($this->unsafe)
                hygienize($expansion, $engine);

            if ($this->constant) return $expansion;

            return $this->mutate($expansion, $crossover, $engine);
    }

    private function compile(array $expansion, Map $context) : TokenStream {
        $cg = (object) [
            'ts' => TokenStream::fromSlice($expansion),
            'context' => $context
        ];

        $cg->ts->trim();

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
                            implode('', $result->cloaked),
                            $result->cloaked[0]->line()
                        )
                    )
                );
                $this->cloaked = true;
            })
            ,
            token(Token::CLOAKED)
            ,
            chain
            (
                rtoken('/^··\w+$/')->as('expander')
                ,
                either(parentheses(), braces())->as('args')
            )
            ->onCommit(function(){
                $this->constant = false;
            })
            ,
            chain
            (
                rtoken('/^·\w+|···\w+$/')->as('label')
                ,
                optional(token('?'))->as('optional')
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
                if (null !== $result->optional)
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

    private function mutate(TokenStream $ts, Ast $context, Engine $engine) : TokenStream {

        static $states, $parser;

        $states = $states ?? new Stack;

        $parser =
            $parser ??
            traverse
            (
                token(Token::CLOAKED)
                ,
                rtoken('/^T_\w+·\w+$/')->as('label')->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();
                    $ts = $cg->ts;

                    $token = $cg->this->lookupContext($result->label, $cg->context, self::E_UNDEFINED_EXPANSION);

                    $ts->previous();
                    $node = $ts->index();

                    if ($token instanceof Token)
                        $node->token = $token;
                    else
                        $ts->extract($node, $node->next);

                    $ts->next();
                })
                ,
                consume
                (
                    chain
                    (
                        rtoken('/^··\w+$/')->as('expander')
                        ,
                        either(parentheses(), braces())->as('args')
                    )
                )
                ->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();

                    $expander = $result->expander;
                    if (\count($result->args) === 0)
                        $cg->this->fail(self::E_EMPTY_EXPANDER_SLICE, (string) $expander, $expander->line());
                    $expansion = TokenStream::fromSlice($result->args);
                    $mutation = $cg->this->mutate($expansion, $cg->context, $cg->engine);

                    $mutation = $cg->this->lookupExpander($expander)($mutation, $cg->engine);
                    $cg->ts->inject($mutation);
                })
                ,
                consume
                (
                    chain
                    (
                        rtoken('/^·\w+|···\w+$/')->as('label')
                        ,
                        optional(token('?'))->as('optional')
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

                    if (null !== $result->optional)
                        $context = $cg->this->lookupContextOptional($result->label, $cg->context);
                    else
                        $context = $cg->this->lookupContext($result->label, $cg->context, self::E_UNDEFINED_EXPANSION);

                    if ($context === null) return;

                    $delimiters = $result->delimiters;

                    // normalize single context
                    if (array_values($context) !== $context) $context = [$context];

                    foreach (array_reverse($context) as $i => $subContext) {
                        $expansion = TokenStream::fromSlice($result->expansion);
                        $mutation = $cg->this->mutate(
                            $expansion,
                            (new Ast(null, $subContext))->withParent($cg->context),
                            $cg->engine
                        );
                        if ($i !== 0) foreach ($delimiters as $d) $mutation->push($d);
                        $cg->ts->inject($mutation);
                    }
                })
                ,
                consume
                (
                    rtoken('/^·\w+|···\w+$/')->as('label')
                )
                ->onCommit(function(Ast $result) use ($states) {
                    $cg = $states->current();

                    $context = $cg->this->lookupContext($result->label, $cg->context, self::E_UNDEFINED_EXPANSION);

                    if ($context instanceof Token) {
                        $cg->ts->inject(TokenStream::fromSequence($context));
                    }
                    elseif (\is_array($context) && \count($context)) {
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

    private function lookupContextOptional(Token $token, Context $context) /*: Token | []Token*/ {
        $symbol = (string) $token;

        return $context->get($symbol);
    }
}
