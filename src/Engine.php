<?php declare(strict_types=1);

namespace Yay;

final class Engine {

    const
        GC_ENGINE_DISABLED = 0,
        GC_ENGINE_ENABLED = 1
    ;

    private
        $blueContext,
        $cycle,
        $expander,
        $filename = ''
    ;

    private
        $globalDirectives = [],
        $literalHitMap = [],
        $typeHitMap = []
    ;

    function __construct() {
        $this->cycle = new Cycle;
        $this->blueContext = new BlueContext;

        $macroParser =
            consume
            (
                chain
                (
                    sigil(
                        token(T_STRING, 'macro')
                        ,
                        optional
                        (
                            repeat
                            (
                                chain(token(':'), label()->as('tag'))
                            )
                        )
                        ->as('tags')
                    )
                    ->as('declaration')
                    ,
                    commit
                    (
                        chain
                        (
                            lookahead
                            (
                                token('{')
                            )
                            ,
                            commit
                            (
                                chain
                                (
                                    braces()->as('pattern')
                                    ,
                                    token(T_SR)
                                    ,
                                    optional
                                    (
                                        chain
                                        (
                                            token(T_FUNCTION)->as('declaration')
                                            ,
                                            parentheses()->as('args')
                                            ,
                                            braces()->as('body')
                                            ,
                                            token(T_SR)
                                        )
                                    )
                                    ->as('compiler_pass')
                                    ,
                                    braces()->as('expansion')
                                )
                            )
                            ->as('body')
                            ,
                            optional
                            (
                                token(';')
                            )
                        )
                        ->as('macro')
                    )
                )
                ,
                CONSUME_DO_TRIM
            )
            ->onCommit(function(Ast $macroAst) {

                $tags = Map::fromValues(array_map(
                    function(Ast $node) :string { return (string) $node->{'* tag'}->token(); },
                    iterator_to_array($macroAst->{'* declaration tags'}->list())
                ));

                if ($tags->contains('grammar'))
                    $pattern = new GrammarPattern($macroAst->{'declaration'}[0]->line(), $macroAst->{'macro body pattern'}, $tags, Map::fromEmpty());
                else
                    $pattern = new Pattern($macroAst->{'declaration'}[0]->line(), $macroAst->{'macro body pattern'}, $tags, Map::fromEmpty());

                $compilerPass = new CompilerPass($macroAst->{'* macro body compiler_pass'});
                $expansion = new Expansion($macroAst->{'macro body expansion'}, $tags);
                $macro = new Macro($tags, $pattern, $compilerPass, $expansion);

                $this->registerDirective($macro);

                if ($macro->tags()->contains('global')) $this->globalDirectives[] = $macro;
            })
        ;

        $this->expander = function(TokenStream $ts) use($macroParser) {
            $token = $ts->current();
            while ($token instanceof Token) {
                $tstring = $token->value();

                // here we attempt to parse, compile and allocate new macros
                if (YAY_DOLLAR === $tstring) $macroParser->parse($ts);

                // here attempt to match and expand userland macros
                // but just in case at least one macro passes the entry point heuristics
                if (isset($this->literalHitMap[$tstring])) {
                    foreach ($this->literalHitMap[$tstring] as $directives) {
                        foreach ($directives as $directive) {
                            $directive->apply($ts, $this);
                        }
                    }
                }
                else if (isset($this->typeHitMap[$token->type()])) {
                    foreach ($this->typeHitMap[$token->type()] as $directives) {
                        foreach ($directives as $directive) {
                            $directive->apply($ts, $this);
                        }
                    }
                }

                $token = $ts->next();
            }
        };
    }

    function registerDirective(Directive $directive) {
        $specificity = $directive->pattern()->specificity();
        $identity = $directive->id();
        $expectations = $directive->pattern()->expected()->all();

        foreach ($expectations as $expected) {
            if ($key = (string) $expected) {
                $this->literalHitMap[$key][$specificity][$identity] = $directive;
                krsort($this->literalHitMap[$key]);
            }
            else {
                $this->typeHitMap[$expected->type()][$specificity][$identity] = $directive;
                krsort($this->typeHitMap[$expected->type()]);
            }
        }
    }

    function blueContext() : BlueContext {
        return $this->blueContext;
    }

    function cycle() : Cycle {
        return $this->cycle;
    }

    function currentFileName() : string {
        return $this->filename;
    }

    function expand(string $source, string $filename = '', int $gc = self::GC_ENGINE_ENABLED) : string {

        $this->filename = $filename;

        foreach ($this->globalDirectives as $d) $this->registerDirective($d);

        $ts = TokenStream::{$filename && self::GC_ENGINE_ENABLED === $gc ? 'fromSource' : 'FromSourceWithoutOpenTag'}($source);

        ($this->expander)($ts);
        $expansion = (string) $ts;

        if (self::GC_ENGINE_ENABLED === $gc) {
            // almost everything is local per file so state must be destroyed after expansion
            // unless the flag ::GC_ENGINE_ENABLED forces a recycle during nested expansions
            // global directives are allocated again later to give impression of persistence
            // ::GC_ENGINE_DISABLED indicates the current pass is an internal Engine recursion
            $this->cycle = new Cycle;
            $this->literalHitMap= $this->typeHitMap = [];
            $this->blueContext = new BlueContext;
        }

        $this->filename = '';

        return $expansion;
    }
}
