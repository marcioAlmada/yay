<?php declare(strict_types=1);

namespace Yay;

class NodeEnd implements Index {

    public $token, $previous;

    private $next;

    function __debugInfo() { return []; }
}
