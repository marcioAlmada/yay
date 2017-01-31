<?php declare(strict_types=1);

namespace Yay;

class Expected {

    protected $tokens, $negation = false;

    function __construct(Token ...$tokens) {
        $this->tokens = $tokens;
    }

    function append(self $tokens) {
        foreach ($tokens->tokens as $token) $this->tokens[] = $token;
    }

    function all() : array {
        return $this->tokens;
    }

    function negate() : self {
        $expected = clone $this;
        $expected->negation = true;

        return $expected;
    }

    function __toString() : string {
        return
            ($this->negation ? 'not ' : '') .
            implode(
                ' or ' . ($this->negation ? 'not ' : ''),
                array_unique(
                    array_map(
                        function(Token $t) {
                            return $t->dump();
                        },
                        $this->all()
                    )
                )
            )
        ;
    }

    function raytrace() : string {
        return
            ($this->negation ? 'not ' : '') .
            implode(
                ' | ',
                array_map(
                    function(Token $t){ return $t->dump(); },
                    $this->tokens
                )
            )
        ;
    }
}
