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
        $parser,
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

        $this->parser =
            traverse
            (
                // this midrule is where the preprocessor does the expansion!
                midrule(function(TokenStream $ts) {
                    $token = $ts->current();

                    while (null !== $token) {

                        $tstring = $token->value();

                        // skip when something looks like a new macro to be parsed
                        if ('macro' === $tstring) break;

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
                })
                ,
                // here we parse, compile and allocate new macros
                consume
                (
                    chain
                    (
                        token(T_STRING, 'macro')->as('declaration')
                        ,
                        optional
                        (
                            repeat
                            (
                                rtoken('/^·\w+$/')
                            )
                        )
                        ->as('tags')
                        ,
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
                                        token(T_FUNCTION)
                                        ,
                                        parentheses()->as('args')
                                        ,
                                        braces()->as('body')
                                        ,
                                        token(T_SR)
                                    )
                                )
                                ->as('compiler')
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
                    ,
                    CONSUME_DO_TRIM
                )
                ->onCommit(function(Ast $macroAst) {
                    $scope = Map::fromEmpty();
                    $tags = Map::fromValues(array_map('strval', $macroAst->{'tags'}));

                    if ($tags->contains('·grammar')) {
                        $pattern = new GrammarPattern($macroAst->{'declaration'}->line(), $macroAst->{'body pattern'}, $tags, $scope);
                    }
                    else {
                        $pattern = new Pattern($macroAst->{'declaration'}->line(), $macroAst->{'body pattern'}, $tags, $scope);
                    }

                    $compilerPass = new CompilerPass($macroAst->{'body compiler'});
                    $expansion = new Expansion($macroAst->{'body expansion'}, $tags, $scope);
                    $macro = new Macro($tags, $pattern, $compilerPass, $expansion);

                    $this->registerDirective($macro);

                    if ($macro->tags()->contains('·global')) $this->globalDirectives[] = $macro;
                })
            )
        ;
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

        $this->parser->parse($ts);
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
