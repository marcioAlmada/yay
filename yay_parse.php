<?php declare(strict_types=1);

use Yay\{
    YayException, TokenStream, Ast, Directives, Macro, Ignore,
    const CONSUME_DO_TRIM
};

use function Yay\{
    token, any, optional, operator, either, chain, lookahead, commit,
    braces, consume, traverse
};

function yay_parse(string $source) : string {

    $cg = (object) [
        'line' => 0,
        'directives' => new Directives,
        'TokenStream' => TokenStream::fromSource($source)
    ];

    $cgline = function($result) use($cg) {
        $cg->line = $result->token()->line();
    };

    traverse
    (
        either
        (
            consume
            (
                either
                (
                    chain
                    (
                        token(T_STRING, 'ignore')->onCommit($cgline)
                        ,
                        lookahead
                        (
                            token('{')
                        )
                        ,
                        commit
                        (
                            braces()->as('pattern')
                        )
                        ,
                        optional
                        (
                            token(';')
                        )
                    )
                    ->onCommit(function(Ast $result) use($cg) {
                        $cg->directives->insert(
                            new Ignore($cg->line, $result->pattern));
                    })
                    ,
                    chain
                    (
                        token(T_STRING, 'macro')->onCommit($cgline)
                        ,
                        lookahead
                        (
                            token('{')
                        )
                        ,
                        commit
                        (
                            chain
                            (
                                braces()->as('pattern')
                                ,
                                operator('>>')
                                ,
                                braces()->as('mutation')
                                ,
                                optional
                                (
                                    token(';')
                                )
                            )
                            ->as('rule')
                        )

                    )
                    ->onCommit(function(Ast $result) use($cg) {
                        $cg->directives->insert(
                            new Macro(
                                $cg->line,
                                $result->{'rule pattern'},
                                $result->{'rule mutation'}
                            )
                        );
                    })
                )
                ,
                CONSUME_DO_TRIM
            )
            ,
            any()
                ->onTry(function() use($cg) {
                    $cg->directives->apply($cg->TokenStream);
                })
        )
    )
    ->parse($cg->TokenStream);

    return (string) $cg->TokenStream;
}
