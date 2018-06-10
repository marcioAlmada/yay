<?php declare(strict_types=1);

namespace Yay;

const
    YAY_DOLLAR = '$',
    YAY_SIGIL = YAY_DOLLAR . '(',
    YAY_SIGIL_FOR_EXPANDER = YAY_DOLLAR . YAY_SIGIL,
    YAY_ESCAPE = '\\\\',
    YAY_ESCAPED_SIGIL = YAY_ESCAPE . YAY_SIGIL,
    YAY_ESCAPED_ESIGIL_FOR_EXPANDER = YAY_ESCAPE . YAY_SIGIL_FOR_EXPANDER,
    YAY_PATTERN_COMMIT = YAY_DOLLAR . '!'
;

function seal(Parser $prefix, Parser ...$parsers) : Parser {
    return
        chain(
            ...array_merge(
                [
                    $prefix,
                ],
                $parsers,
                [
                    commit(token(')'))
                ]
            )
        )
    ;
}

function sigil_prefix() : Parser {
    return buffer(YAY_SIGIL);
}

function escaped_sigil_prefix() : Parser {
    return buffer(YAY_ESCAPED_SIGIL);
}

/**
 * Defines the preprocessor sigil started by `$(` and ended by `)`
 */
function sigil(Parser ...$parsers) : Parser {
    return seal(sigil_prefix()->as('sigil'), ...$parsers);
}

function expander_sigil_prefix() : Parser {
    return buffer(YAY_SIGIL_FOR_EXPANDER);
}

function escaped_expander_sigil_prefix() : Parser {
    return buffer(YAY_ESCAPED_ESIGIL_FOR_EXPANDER);
}

/**
 * Defines the preprocessor expander sigil started by `$$(` and ended by `)`
 */
function expander_sigil(Parser ...$parsers) : Parser {
    return seal(expander_sigil_prefix()->as('expander_sigil'), ...$parsers);
}

/**
 * Defines the preprocessor aliased capture syntax as in `as foo` used like `$(T_STRING as foo)`
 */
function alias() : Parser {
    return
        chain(
            token(T_AS),
            label()->as('name')
        )
        ->as('alias')
    ;
}

function token_constant() : Parser {
    return rtoken('/^T_\w+$/')->as('token_constant');
}

function parsec() : Parser {
    return
        $parser =
            chain
            (
                ns()->as('type')
                ,
                token('(')
                ,
                optional
                (
                    ls
                    (
                        either
                        (
                            pointer
                            (
                                $parser // recursion !!!
                            )
                            ,
                            chain
                            (
                                token(T_FUNCTION)
                                ,
                                parentheses()->as('args')
                                ,
                                braces()->as('body')
                            )
                            ->as('function')
                            ,
                            string()->as('string')
                            ,
                            chain(token_constant(), alias())->as('named_token_constant')
                            ,
                            token_constant()
                            ,
                            sigil(token(T_STRING, 'this'))->as('this')
                            ,
                            label()->as('literal')
                        )
                        ,
                        token(',')
                    )
                )
                ->as('args')
                ,
                token(')')
                ,
                optional(alias())
            )
            ->as('parsec')
    ;
}

function pattern_commit() : Parser {
    return buffer(YAY_PATTERN_COMMIT);
}
