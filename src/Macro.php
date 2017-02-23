<?php declare(strict_types=1);

namespace Yay;

class Macro implements Directive {

    protected
        $pattern,
        $expansion,
        $cycle,
        $tags,
        $terminal = true,
        $hasExpansion = true
    ;

    private
        $id
    ;

    protected static $_id = 0;

    function __construct(Map $tags, Pattern $pattern, Expansion $expansion, Cycle $cycle) {
        $this->id = (__CLASS__)::$_id++;
        $this->tags = $tags;
        $this->pattern = $pattern;
        $this->expansion = $expansion;
        $this->cycle = $cycle;

        $this->terminal = !$this->expansion->isRecursive();
        $this->hasExpansion = !$this->expansion->isEmpty();
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

    function apply(TokenStream $ts, Directives $directives, BlueContext $blueContext) {
        $from = $ts->index();

        $crossover = $this->pattern->match($ts);

        if (null === $crossover || $crossover instanceof Error) return;

        if ($this->hasExpansion) {

            $blueMacros = $this->getAllBlueMacrosFromCrossover($crossover->all(), $blueContext);

            if ($this->terminal && isset($blueMacros[$this->id])) { // already expanded
                $ts->jump($from);

                return;
            }

            $ts->unskip();
            $to = $ts->index();
            $ts->extract($from, $to);

            $expansion = $this->expansion->expand($crossover, $this->cycle, $directives, $blueContext);

            $blueMacros[$this->id] = true;

            // paint blue context with tokens from expansion and disabled macros
            $node = $expansion->index();
            while ($node instanceof Node) {
                $blueContext->addDisabledMacros($node->token, $blueMacros);
                $node = $node->next;
            }

            $ts->inject($expansion);
        }
        else {
            $ts->unskip();
            while (null !== ($token = $ts->current()) && $token->is(T_WHITESPACE)) {
                $ts->step();
            }
            $to = $ts->index();
            $ts->extract($from, $to);
        }

        $this->cycle->next();
    }

    private function getAllBlueMacrosFromCrossover($nodes, BlueContext $blueContext): array {
        $macros = [];

        foreach ($nodes as $node)
            if ($node instanceof Token)
                $macros += $blueContext->getDisabledMacros($node);
            else
                $macros += $this->getAllBlueMacrosFromCrossover($node, $blueContext);

        return $macros;
    }
}
