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
    $ts->reset();
    $buffer = '';
    while($t = $ts->current()) {
        $str = (string) $t;
        if (! preg_match('/^\w+$/', $str))
            throw new YayException(
                "Only valid identifiers are mergeable, '{$t->dump()}' given.");

        $buffer .= $str;
        $ts->next();
    }

    return TokenStream::fromSequence(new Token(T_STRING, $buffer));
}

function hygienize(TokenStream $ts, array $context) : TokenStream {
    $ts->reset();

    $cg = (object)[
        'node' => null,
        'context' => $context,
        'ts' => $ts
    ];

    $saveNode = function() use($cg) { $cg->node = $cg->ts->index(); };

    traverse
    (
        // hygiene must skip whatever is passed through the ··unsafe() expander
        chain(token(T_STRING, '··unsafe'), parentheses())
        ,
        either
        (
            token(T_VARIABLE)->onTry($saveNode)->as('target')
            ,
            chain(identifier()->onTry($saveNode)->as('target'), token(':'))
            ,
            chain(token(T_GOTO), identifier()->onTry($saveNode)->as('target'))
        )
        ->onCommit(function(Ast $result) use ($cg) {
            if (($t = $cg->node->token) && (($value = (string) $t) !== '$this'))
                $cg->node->token = new Token($t->type(), "{$value}·{$cg->context['scope']}", $t->line());
        })
    )
    ->parse($ts);

    $ts->reset();

    return $ts;
}

function unsafe(TokenStream $ts) : TokenStream { return $ts; }

function expand(TokenStream $ts, array $context) : TokenStream {
    $ts = TokenStream::fromSource(yay_parse('<?php ' . (string) $ts, $context['directives']));
    $ts->shift();

    return $ts;
}
