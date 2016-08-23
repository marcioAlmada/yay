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

    function __construct(Map $tags, Pattern $pattern, Expansion $expansion, Cycle $cycle) {
        static $id = 0;

        $this->tags = $tags;
        $this->pattern = $pattern;
        $this->expansion = $expansion;
        $this->cycle = $cycle;

        $this->terminal = !$this->expansion->isRecursive();
        $this->hasExpansion = !$this->expansion->isEmpty();

        $this->id = $id++;
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

    private function tokenContextWalkRecursive($items, $context){
        foreach ($items as $item) {
            if ($item instanceof Token) {
                $context->inherit($item->context());
            }
            else {
                $this->tokenContextWalkRecursive($item, $context);
            }
        }
    }

    function apply(TokenStream $ts, Directives $directives) {
        $from = $ts->index();

        $crossover = $this->pattern->match($ts);

        if (null === $crossover || $crossover instanceof Error) return;

        if ($this->hasExpansion) {
            // infer blue context from matched tokens
            $context = new BlueContext;
            $this->tokenContextWalkRecursive($crossover->all(), $context);

            if ($this->terminal && $context->contains($this->id())) { // already expanded
                $ts->back($from);

                return;
            }

            $context->add($this->id());
            $ts->unskip(...TokenStream::SKIPPABLE);
            $to = $ts->index();
            $ts->extract($from, $to);

            $expansion = $this->expansion->expand($crossover, $this->cycle, $directives);
            $this->cycle->next();

            // paint blue context of expasion tokens
            $node = $expansion->index();
            while ($node->token) {
                $node->token->context()->inherit($context);
                $node = $node->next;
            }

            $ts->inject($expansion);
        }
        else {
            $ts->unskip(...TokenStream::SKIPPABLE);
            $ts->skip(T_WHITESPACE);
            $to = $ts->index();
            $ts->extract($from, $to);
        }
    }
}
