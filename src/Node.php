<?php declare(strict_types=1);

namespace Yay;

class Node implements Index {

    public $token, $next, $previous;

    function __construct(Token $token) { $this->token = $token; }

    function __debugInfo() { return [$this->token]; }
}
