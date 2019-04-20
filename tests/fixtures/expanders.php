<?php namespace Yay\tests\fixtures\expanders;

use Yay\{Token, TokenStream};

function my_hello_tokenstream_expander(TokenStream $ts) : TokenStream {
    $str = str_replace("'", "\'", (string) $ts);

    return
        TokenStream::fromSequence(
            new Token(
                T_CONSTANT_ENCAPSED_STRING, "'Hello, {$str}. From TokenStream.'", $ts->first()->line()
            )
        )
    ;
}

function my_cheers_tokenstream_expander(TokenStream $ts) : TokenStream {
    $str = str_replace("'", "", (string) $ts);

    return
        TokenStream::fromSequence(
            new Token(
                T_CONSTANT_ENCAPSED_STRING, "'{$str} Cheers!'", $ts->first()->line()
            )
        )
    ;
}

function my_hello_ast_expander(\Yay\Ast $ast) : \Yay\Ast {
    return new \Yay\Ast($ast->label(), new Token(
        T_CONSTANT_ENCAPSED_STRING, "'Hello, {$ast->token()}. From Ast.'", $ast->token()->line()
    ));
}

function my_cheers_ast_expander(\Yay\Ast $ast) : \Yay\Ast {
    $str = str_replace("'", "", (string) $ast->token());

    return new \Yay\Ast($ast->label(), new Token(
        T_CONSTANT_ENCAPSED_STRING, "'{$str} Cheers!'", $ast->token()->line()
    ));
}

function my_foo_expander(\Yay\Ast $ast) {
    return TokenStream::fromSourceWithoutOpenTag(sprintf("'called %s(%s)'", __FUNCTION__, $ast->implode()));
}

function my_bar_expander(\Yay\Ast $ast) {
    return TokenStream::fromSourceWithoutOpenTag(sprintf("'called %s(%s)'", __FUNCTION__, $ast->implode()));
}
