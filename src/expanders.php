<?php declare(strict_types=1);

namespace Yay\DSL\Expanders;

use Yay\{Engine, Token, TokenStream, Ast, YayException, Cycle, Parser, Context};
use function Yay\{
    token, rtoken, identifier, chain, either, any, parentheses, braces, traverse, midrule, buffer
};

function stringify(TokenStream $ts) : TokenStream {
    $str = str_replace("'", "\'", (string) $ts);

    return
        TokenStream::fromSequence(
            new Token(
                T_CONSTANT_ENCAPSED_STRING, "'{$str}'", $ts->first()->line()
            )
        )
    ;
}

function unvar(TokenStream $ts) : TokenStream {
    $str = preg_replace('/^\$+/', '', (string) $ts);

    return
        TokenStream::fromSequence(
            new Token(
                T_CONSTANT_ENCAPSED_STRING, $str
            )
        )
    ;
}

function concat(TokenStream $ts) : TokenStream {
    $ts->reset();
    $buffer = '';
    $line  = $ts->current()->line();
    while($t = $ts->current()) {
        $str = (string) $t;
        if (! preg_match('/^\w+$/', $str))
            throw new YayException(
                "Only valid identifiers are mergeable, '{$t->dump()}' given.");

        $buffer .= $str;
        $ts->next();
    }

    return TokenStream::fromSequence(new Token(T_STRING, $buffer, $line));
}

function hygienize(TokenStream $ts, Engine $engine) : TokenStream {
    $ts->reset();

    $cg = (object)[
        'node' => null,
        'scope' => $engine->cycle()->id(),
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
        // hygiene must skip whatever is passed through the $$(unsafe()) expander
        chain(buffer('$$'), token('('), token(T_STRING, 'unsafe'), either(parentheses(), braces()), token(')'))
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
                $cg->node->token = new Token($t->type(), "{$value}___{$cg->scope}", $t->line());

            $cg->node = null;
        })
    )
    ->parse($ts);

    $ts->reset();

    return $ts;
}

function unsafe(TokenStream $ts) : TokenStream { return $ts; }

function whitespace(TokenStream $ts) : TokenStream {
    return
        TokenStream::fromSequence(
            new Token(
                T_WHITESPACE, str_repeat(PHP_EOL, substr_count((string) $ts, PHP_EOL) + 1), $ts->first()->line()
            )
        )
    ;
}

function expand(TokenStream $ts, Engine $engine) : TokenStream {

    $ts = TokenStream::fromSource($engine->expand((string) $ts, $engine->currentFileName(), Engine::GC_ENGINE_DISABLED));

    return $ts;
}
