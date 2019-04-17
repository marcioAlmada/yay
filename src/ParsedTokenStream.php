<?php declare(strict_types=1);

namespace Yay;

class ParsedTokenStream extends TokenStream {
    protected
        $ast
    ;

    function setAst(Ast $ast) {
        $this->ast = $ast;
    }

    function getAst() {
        return $this->ast;
    }
}
