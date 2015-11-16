<?php declare(strict_types=1);

namespace Yay;

class Node {

    public
        $token,
        $previous,
        $next
    ;

    function __construct(Token $token) {
        $this->token = $token;
    }

    function __debugInfo() {
        return [$this->token];
    }
}
