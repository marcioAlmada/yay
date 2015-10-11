<?php declare(strict_types=1);

namespace Yay\DSL\Expanders;

use Yay\{Token, TokenStream, YayException};

function stringify(/* TokenStream|Token */ $i) : TokenStream {
    $str = str_replace("'", "\'", (string) $i);

    return
        TokenStream::fromSequence(
            new Token(
                T_CONSTANT_ENCAPSED_STRING, "'{$str}'"
            )
        )
    ;
}

function concat(TokenStream $ts) : TokenStream {
    $buffer = [];
    while($t = $ts->current()) {
        $str = (string) $t;
        if (! preg_match('/^\w+$/', $str))
            throw new YayException(
                "Only valid identifiers are mergeable, '{$t->dump()}' given.");

        $buffer[] = $str;
        $ts->next();
    }

    return TokenStream::fromSequence(
        new Token(
            T_STRING, implode('', $buffer)
        )
    );
}
