<?php declare(strict_types=1);

namespace Yay;

class Macro implements Directive {

    protected
        $pattern,
        $expansion,
        $tags,
        $isTerminal
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

            $blueContext = $engine->blueContext();
            $blueMacros = $this->getAllBlueMacrosFromCrossover($crossover->unwrap(), $blueContext);

            if ($this->isTerminal && isset($blueMacros[$this->id])) { // already expanded
                $ts->jump($from);

                return;
            }

            $ts->unskip();
            $to = $ts->index();
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
