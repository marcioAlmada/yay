<?php declare(strict_types=1);

namespace Yay;

class NodeStart implements Index {

    public $token, $next;

    private $previous;

    function __debugInfo() { return []; }
}
