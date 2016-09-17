<?php declare(strict_types=1);

namespace Yay\DSL\Expanders;

use Yay\{Token, TokenStream, Ast, YayException, Cycle, Parser, Context};
use function Yay\{
    token, rtoken, identifier, chain, either, any, parentheses, braces, traverse, midrule
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

    $saveNode = function(Parser $parser) use($cg) {
        return midrule(function($ts) use ($cg, $parser) {
            $cg->node = $ts->index();

            return $parser->parse($ts);
        });
    };

    traverse
    (
        // hygiene must skip whatever is passed through the ··unsafe() expander
        chain(token(T_STRING, '··unsafe'), either(parentheses(), braces()))
        ,
        either
        (
            $saveNode(token(T_VARIABLE))
            ,
            chain($saveNode(identifier()), token(':'))
            ,
            chain(token(T_GOTO), $saveNode(identifier()))
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

function expand(TokenStream $ts, Context $context) : TokenStream {
    $ts = TokenStream::fromSource(yay_parse('<?php ' . (string) $ts, $context->get('directives'), $context->get('blueContext')));
    $ts->shift();

    return $ts;
}
