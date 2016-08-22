<?php declare(strict_types=1);

namespace Yay;

class Stack {
    private
        $stack = []
    ;

    function push($value) {
        $this->stack[] = $value;
    }

    function pop() {
        array_pop($this->stack);
    }

    function current() {
        return end($this->stack);
    }
}
