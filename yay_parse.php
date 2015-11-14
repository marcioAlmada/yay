<?php declare(strict_types=1);

use Yay\{ Token, TokenStream, Directives, Macro, Expected, Error, Cycle };
use Yay\{ const LAYER_DELIMITERS };

function yay_parse(string $source, string $salt) : string {
start:

    if ($gc = gc_enabled()) gc_disable();
    $ts = TokenStream::fromSource($source);
    $directives = new Directives;
    $cycle = new Cycle($salt);

declaration:

    $from = $ts->index();

    if (! ($token = $ts->current())) goto end;

    if ($token->contains('macro')) {
        $declaration = $token;
        $ts->next();
        goto tags;
    }
    goto any;

tags:

    $tags = [];
    while (($token = $ts->current()) && preg_match('/^·\w+$/', (string) $token)) {
        $tags[] = $token;
        $ts->next();
    }

pattern:

    $index = $ts->index();
    $pattern = [];
    $level = 1;

    if (! ($token = $ts->current()) || ! $token->is('{')) goto any;

    $ts->next();

    while (($token = $ts->current()) && $level += (LAYER_DELIMITERS[$token->type()] ?? 0)){
        $pattern[] = $token;
        $token = $ts->step();
    }

    if (! $token || ! $token->is('}')) {
        $ts->jump($index);
        (new Error(new Expected(new Token('}')), $ts->current(), $ts->last()))->halt();
    }

    $ts->next();

__: // >>

    $index = $ts->index();
    $operator = '>>';
    $max = strlen($operator);
    $buffer = '';

    while ((mb_strlen($buffer) <= $max) && $token = $ts->current()) {
        $buffer .= (string) $token;
        $ts->step();
        if($buffer === $operator) {
            $ts->skip(T_WHITESPACE);
            goto expansion;
        }
    }

    $ts->jump($index);
    (new Error(new Expected(Token::Operator($operator)), $ts->current(), $ts->last()))->halt();

expansion:

    $index = $ts->index();
    $expansion = [];
    $level = 1;

    if (! ($token = $ts->current()) || ! $token->is('{')) {
        $ts->jump($index);
        (new Error(new Expected(new Token('}')), $ts->current(), $ts->last()))->halt();
    }

    $ts->next();

    while (($token = $ts->current()) && $level += (LAYER_DELIMITERS[$token->type()] ?? 0)){
        $expansion[] = $token;
        $token = $ts->step();
    }

    if (! $token || ! $token->is('}')) {
        $ts->jump($index);
        (new Error(new Expected(new Token('}')), $ts->current(), $ts->last()))->halt();
    }

    // optional ';'
    $token = $ts->next();
    if ($token->is(';')) $ts->next();

    // cleanup
    $ts->unskip(...TokenStream::SKIPPABLE);
    $ts->skip(T_WHITESPACE);
    $ts->extract($from, $ts->index());
    $directives->add(
        new Macro(
            $declaration->line(),
            $tags,
            $pattern,
            $expansion,
            $cycle
        )
    );
    goto declaration;

any:

    if ($token) {
        $directives->apply($ts);
        $ts->next();
    }
    goto declaration;

end:

    $expansion = (string) $ts;

    if ($gc) gc_enable();

    return $expansion;
}
