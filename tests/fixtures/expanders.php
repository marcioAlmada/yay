<?php namespace Yay\tests\fixtures\expanders;

use Yay\{Ast, Engine, Token, TokenStream, ParsedTokenStream};

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

function reverse_ast_expander(ParsedTokenStream $stream, Engine $engine) : ParsedTokenStream {
    $ast = $stream->getAst();

    $leaf = $ast->{"* outer inner 0 0"}->token();
    $ast->{"outer inner 0 0"} = new Token($leaf->type(), strrev($leaf->value()));

    return ParsedTokenStream::fromSource(
        join(" ", $ast->tokens())
    );
}

function upper_ast_expander(ParsedTokenStream $stream, Engine $engine) : ParsedTokenStream {
    $ast = $stream->getAst();

    $leaf = $ast->{"* outer inner 0 0"}->token();
    $ast->{"outer inner 0 0"} = new Token($leaf->type(), strtoupper($leaf->value()));

    return ParsedTokenStream::fromSource(
        join(" ",  $ast->tokens())
    );
}

function wrap_ast_expander(ParsedTokenStream $stream, Engine $engine) : ParsedTokenStream {
    $ast = $stream->getAst();

    $leaf = $ast->{"* outer inner 0 0"}->token();
    $ast->{"outer inner 0 0"} = new Token($leaf->type(), "[{$leaf->value()}]");

    return ParsedTokenStream::fromSource(
        join(" ",  $ast->tokens())
    );
}
