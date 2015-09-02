<?php declare(strict_types=1);

namespace Yay\DSL\Expanders;

use Yay\{Token, TokenStream};

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
