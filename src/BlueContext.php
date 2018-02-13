<?php declare(strict_types=1);

namespace Yay;

class BlueContext {
    private $map = [];

    function addDisabledMacros($token, $macros) {
        assert($token instanceof Token);
        assert(\is_array($macros));

        foreach ($macros as $id => $_) $this->map[$token->id()][$id] = true;
    }

    function getDisabledMacrosFromToken($token) {
        assert($token instanceof Token);

        if (isset($this->map[$token->id()])) return $this->map[$token->id()];

        return [];
    }

    function getDisabledMacrosFromTokens($tokens) {
        assert(\is_array($tokens));

        return array_reduce($tokens, function($macros, $token) {
            return $macros += $this->getDisabledMacrosFromToken($token);
        }, []);
    }
}
