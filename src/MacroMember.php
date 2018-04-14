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

    /**
     * Defines the preprocessor sigil started by `$(` and ended by `)`
     */
    protected function sigil(Parser ...$parsers) : Parser {
        return
            chain(
                ...array_merge(
                    [
                        token('$')->as('declaration'),
                        token('(')
                    ],
                    $parsers,
                    [
                        commit( token(')'))
                    ]
                )
            )
        ;
    }

    /**
     * Defines the preprocessor aliased capture syntax as in `as foo` used like `$(T_STRING as foo)`
     */
    protected function alias() : Parser {
        return
            chain(
                token(T_AS),
                label()->as('name')
            )
            ->as('alias')
        ;
    }

    protected function fail(string $error, ...$args) {
        throw new YayParseError(sprintf($error, ...$args));
    }
}
