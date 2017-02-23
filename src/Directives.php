<?php declare(strict_types=1);

namespace Yay;

class Directives {

    protected
        $literalHitMap = [],
        $typeHitMap = []
    ;

    function add(Directive $directive) {
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

    function apply(TokenStream $ts, BlueContext $blueContext) {
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
                        $directive->apply($ts, $this, $blueContext);
                    }
                }
            }
            else if (isset($this->typeHitMap[$token->type()])) {
                foreach ($this->typeHitMap[$token->type()] as $directives) {
                    foreach ($directives as $directive) {
                        $directive->apply($ts, $this, $blueContext);
                    }
                }
            }

            $token = $ts->next();
        }
    }
}
