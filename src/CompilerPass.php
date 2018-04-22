<?php declare(strict_types=1);

namespace Yay;

class CompilerPass extends AnonymousFunction {
    function __invoke(...$args) {
        if ($this->closure) return $this->apply(...$args);
    }

    function apply(Ast $ast, TokenStream $ts, Index $startNode, Index $endNode, Engine $engine) {
        if ($this->closure) return ($this->closure)($ast, $ts, $startNode, $endNode, $engine);
    }
}
