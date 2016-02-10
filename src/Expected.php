<?php declare(strict_types=1);

namespace Yay;

class Expected {

    protected $tokens;

    function __construct(Token ...$tokens) {
        $this->tokens = $tokens;
    }

    function append(self $tokens) {
        foreach ($tokens->tokens as $token) $this->tokens[] = $token;
    }

    function all() : array {
        return $this->tokens;
    }

    function raytrace() : string {
        return
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
