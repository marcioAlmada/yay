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

    function __toString() : string {
        return (string) $this->token . (string) $this->next;
    }

    function __debugInfo() {
        return [$this->token];
    }
}
