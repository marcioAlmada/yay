<?php declare(strict_types=1);

namespace Yay;

class BlueContext {
    private $map = [];

    function addDisabledMacros(Token $token, array $macros) {
        if (! isset($this->map[$token->id()])) $this->map[$token->id()] = [];

        $this->map[$token->id()] += $macros;
    }

    function isMacroDisabled(Token $token, Macro $macro): bool {
        return isset($this->map[$token->id()][$macro->id()]);
    }

    function getDisabledMacros(Token $token): array {
        return $this->map[$token->id()] ?? [];
    }
}
