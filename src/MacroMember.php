<?php declare(strict_types=1);

namespace Yay;

abstract class MacroMember {

    const
        PRETTY_PRINT =
                JSON_PRETTY_PRINT
            |   JSON_BIGINT_AS_STRING
            |   JSON_UNESCAPED_UNICODE
            |   JSON_UNESCAPED_SLASHES
    ;

    protected function fail(string $error, ...$args) {
        throw new YayParseError(sprintf($error, ...$args));
    }
}
