<?php declare(strict_types=1);

namespace Yay;

class Macro implements Directive {

    protected
        $pattern,
        $expansion,
        $compilerPass,
        $tags,
        $isTerminal
    ;

    private
        $id
    ;

    protected static $_id = 0;

    function __construct(Map $tags, PatternInterface $pattern, CompilerPass $compilerPass = null, Expansion $expansion) {
        $this->id = (__CLASS__)::$_id++;
        $this->tags = $tags;
        $this->pattern = $pattern;
        $this->compilerPass = $compilerPass;
        $this->expansion = $expansion;

        $this->isTerminal = !$this->expansion->isRecursive();
    }

    function id() : int {
        return $this->id;
    }

    function tags() : Map {
        return $this->tags;
    }

    function pattern() : Pattern {
        return $this->pattern;
    }

    function expansion() : Expansion {
        return $this->expansion;
    }

    function apply(TokenStream $ts, Engine $engine) {

        $from = $ts->index();

        $crossover = $this->pattern->match($ts);

        if ($crossover instanceof Ast ) {
            $ts->unskip();
            $to = $ts->index();

            ($this->compilerPass)($crossover, $ts, $from, $to->previous, $engine);

            $blueContext = $engine->blueContext();
            $blueMacros = $blueContext->getDisabledMacrosFromTokens($crossover->tokens());

            if ($this->isTerminal && isset($blueMacros[$this->id])) { // already expanded
                $ts->jump($from);

                return;
            }

            $ts->extract($from, $to);

            $expansion = $this->expansion->expand($crossover, $engine);

            $blueMacros[$this->id] = true;

            $node = $expansion->index();
            while ($node instanceof Node) {
                // paint blue context with tokens from expansion and disabled macros
                $blueContext->addDisabledMacros($node->token, $blueMacros);
                $node = $node->next;
            }

            $ts->inject($expansion);

            $engine->cycle()->next();
        }
    }
}
