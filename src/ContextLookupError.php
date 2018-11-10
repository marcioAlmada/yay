<?php declare(strict_types=1);

namespace Yay;

class ContextLookupError {
    function __construct(array $symbols) {
        $this->symbols = $symbols;
    }

    function symbols() : array {
        return $this->symbols;
    }
}
