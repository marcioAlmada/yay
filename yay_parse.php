<?php declare(strict_types=1);

use Yay\{
    YayException, TokenStream, Ast, Directives, Macro, Ignore,
    const CONSUME_DO_TRIM
};

use function Yay\{
    token, rtoken, any, optional, operator, either, chain, lookahead, commit,
    braces, consume, repeat, traverse
};

function yay_parse(string $source) : string {

    if ($gc = gc_enabled()) gc_disable();

    $tstream = TokenStream::fromSource($source);
    $directives = new Directives;

    traverse
    (
        either
        (
            consume
            (
                chain
                (
                    token(T_STRING, 'macro')->as('declaration')
                    ,
                    optional
                    (
                        repeat
                        (
                            rtoken('/^·\w+$/')
                        )
                    )
                    ->as('tags')
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
                            braces()->as('expansion')
                            ,
                            optional
                            (
                                token(';')
                            )
                        )
                    )
                    ->as('body')
                )
                ->onCommit(function(Ast $macro) use($directives) {
                    $directives->add(
                        new Macro(
                            $macro->{'declaration'}->line(),
                            $macro->{'tags'},
                            $macro->{'body pattern'},
                            $macro->{'body expansion'}
                        )
                    );
                })
                ,
                CONSUME_DO_TRIM
            )
            ,
            any()
                ->onCommit(function() use($directives, $tstream) {
                    $directives->apply($tstream);
                })
        )
    )
    ->parse($tstream);

    $expansion = (string) $tstream;

    if ($gc) gc_enable();

    return $expansion;
}
