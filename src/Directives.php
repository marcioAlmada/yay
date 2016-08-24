<?php declare(strict_types=1);

namespace Yay;

class Directives {

    protected
        $directives = [],
        $raytraceLiteral = [],
        $raytraceNonliteral = []
    ;

    function add(Directive $directive) {
        $expectations = $directive->pattern()->expected()->all();

        foreach ($expectations as $expected)
            if ($v = (string) $expected)
                $this->raytraceLiteral[$v] = true;
            else
                $this->raytraceNonliteral[$expected->type()] = true;

        $this->directives[$directive->pattern()->specificity()][$directive->id()] = $directive;
        krsort($this->directives);
    }

    function apply(TokenStream $ts, Token $t, BlueContext $blueContext) {
        if (
            isset($this->raytraceLiteral[(string) $t]) ||
            isset($this->raytraceNonliteral[$t->type()])
        ) {
            foreach ($this->directives as $directives) {
                foreach ($directives as $directive) {
                    $directive->apply($ts, $this, $blueContext);
                }
            }
        }
    }
}
