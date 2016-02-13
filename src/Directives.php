<?php declare(strict_types=1);

namespace Yay;

class Directives {

    protected
        $directives = []
    ;

    function add(Directive $directive) {
        $this->directives[$directive->specificity()][] = $directive;
        krsort($this->directives);
    }

    function apply(TokenStream $ts) {
        foreach ($this->directives as $directives)
            foreach ($directives as $directive)
                $directive->apply($ts, $this);
    }
}
