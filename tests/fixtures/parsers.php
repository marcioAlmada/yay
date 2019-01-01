<?php namespace Yay\tests\fixtures\parsers;

use Yay\{Parser};
use function Yay\{token, chain, optional, either, buffer, ns, expression};

function my_custom_parser() : Parser {
    return token(T_STRING);
}

function my_custom_argument_parser(): Parser
{
    return chain(
        optional(
            chain(
                optional(buffer('?'))->as('nullable'),
                (either(ns(), buffer('array'), buffer('callable'), buffer('self')))->as('name')
            )
            ->as('type')
        ),
        token(T_VARIABLE)->as('argument_name'),
        optional(
            chain(
                buffer('='),
                expression()->as('argument_value')
            )
        )->as('argument_assignment')
    )
    ->as('argument');
}
