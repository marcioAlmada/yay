<?php declare(strict_types=1);

namespace Yay;

abstract class MacroMember {

    protected function fail(string $error, ...$args) {
        throw new YayParseError(sprintf($error, ...$args));
    }
}
