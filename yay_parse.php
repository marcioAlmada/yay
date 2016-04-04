<?php declare(strict_types=1);

use Yay\{ TokenStream, Directives, Macro, Cycle};

use function Yay\{
    token, rtoken, any, operator, optional, commit, chain, braces,
    consume, lookahead, repeat, traverse
};

use const Yay\{ CONSUME_DO_TRIM };

function yay_parse(string $source, Directives $directives = null) : string {

    if ($gc = gc_enabled()) gc_disable(); // important optimization!

    static $globalDirectives = null;

    if (null === $globalDirectives) $globalDirectives = new ArrayObject;

    $cg = (object) [
        'ts' => TokenStream::fromSource($source),
        'directives' => $directives ?: new Directives,
        'cycle' => new Cycle($source),
        'globalDirectives' => $globalDirectives,
    ];

    foreach($cg->globalDirectives as $d) $cg->directives->add($d);

    traverse
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
                    )
                )
                ->as('body')
                ,
                optional
                (
                    token(';')
                )
            )
            ,
            CONSUME_DO_TRIM
        )
        ->onCommit(function($macroAst) use ($cg) {
            $macro = new Macro(
                $macroAst->{'declaration'}->line(),
                $macroAst->{'tags'},
                $macroAst->{'body pattern'},
                $macroAst->{'body expansion'},
                $cg->cycle
            );
            $cg->directives->add($macro);

            if ($macro->hasTag('·global'))
                $cg->globalDirectives[] = $macro;
        })
        ,
        any()
            ->onTry(function() use ($cg) {
                $cg->directives->apply($cg->ts);
            })
    )
    ->parse($cg->ts);

    $expansion = (string) $cg->ts;

    if ($gc) gc_enable();

    return $expansion;
}
