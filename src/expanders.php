<?php declare(strict_types=1);

namespace Yay\DSL\Expanders;

use Yay\{Token, TokenStream, Ast, YayException, Cycle};
use function Yay\{
    token, rtoken, identifier, chain, either, any, parentheses, traverse
};

function stringify(TokenStream $ts) : TokenStream {
    $str = str_replace("'", "\'", (string) $ts);

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

function hygienize(TokenStream $ts, string $scope) : TokenStream {
    $ts->reset();

    traverse
    (
        either
        (
            chain(token(T_STRING, '·unsafe'), parentheses())
            ,
            either
            (
                token(T_VARIABLE)->as('target')
                ,
                chain(identifier()->as('target'), token(':'))
                ,
                chain(token(T_GOTO), identifier()->as('target'))
            )
            ->onCommit(function(Ast $result) use ($scope) {
                (function() use($scope) {
                    if ((string) $this !== '$this')
                        $this->value = (string) $this . '·' . $scope;
                })
                ->call($result->target);
            })
            ,
            any()
        )
    )
    ->parse($ts);

    $ts->reset();

    return $ts;
}

function unsafe(TokenStream $ts) : TokenStream { return $ts; }
