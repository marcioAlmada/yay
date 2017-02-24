<?php declare(strict_types=1);

namespace Yay;

class Macro implements Directive {

    protected
        $pattern,
        $expansion,
        $tags,
        $terminal = true,
        $hasExpansion = true
    ;

    private
        $id
    ;

    protected static $_id = 0;

    function __construct(Map $tags, Pattern $pattern, Expansion $expansion) {
        $this->id = (__CLASS__)::$_id++;
        $this->tags = $tags;
        $this->pattern = $pattern;
        $this->expansion = $expansion;

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

    function apply(TokenStream $ts, Engine $engine) {

        $from = $ts->index();

        $crossover = $this->pattern->match($ts);

        if (null === $crossover || $crossover instanceof Error) return;

        if ($this->hasExpansion) {

            $blueContext = $engine->blueContext();
            $blueMacros = $this->getAllBlueMacrosFromCrossover($crossover->all(), $blueContext);

            if ($this->terminal && isset($blueMacros[$this->id])) { // already expanded
                $ts->jump($from);

                return;
            }

            $ts->unskip();
            $to = $ts->index();
            $ts->extract($from, $to);

            $expansion = $this->expansion->expand($crossover, $engine);

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

        $engine->cycle()->next();
    }

    private function getAllBlueMacrosFromCrossover($node, BlueContext $blueContext): array {
        if ($node instanceof Token)
            return $blueContext->getDisabledMacros($node);
        else if(is_array($node)) {
            $macros = [];
            foreach ($node as $n)
                $macros += $this->getAllBlueMacrosFromCrossover($n, $blueContext);

            return $macros;
        }
    }
}
