<?php namespace Yay\tests\fixtures\expanders;

use Yay\{Token, TokenStream};

function my_hello_expander(TokenStream $ts) : TokenStream {
    $str = str_replace("'", "\'", (string) $ts);

    return
        TokenStream::fromSequence(
            new Token(
                T_CONSTANT_ENCAPSED_STRING, "'Hello, {$str}'", $ts->first()->line()
            )
        )
    ;
}
