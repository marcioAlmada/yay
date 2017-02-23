<?php declare(strict_types=1);

namespace Yay;

class Node implements Index {

    public $token, $next, $previous, $skippable;

    function __construct($token) {
        assert($token instanceof Token);
        $this->token = $token;
        $this->skippable = $token->isSkippable(); // cache skipability
    }

    function __debugInfo() { return [$this->token]; }
}
