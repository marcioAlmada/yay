--TEST--
Test for bug found at https://github.com/ircmaxell/php-compiler/pull/29 --pretty-print
--FILE--
<?php

$(macro :unsafe :recursive) {
    $(optional(buffer('unsigned')) as unsigned) compile {
        $(
            repeat(
                either(
                    chain(
                        T_VARIABLE as result,
                        token('='),
                        either(
                            chain(
                                T_VARIABLE as binary_left,
                                either(token('^') as binary_xor) as binary_op,
                                either(T_VARIABLE as binary_variable, T_LNUMBER as binary_number) as binary_right
                            ) as binary
                        ),
                        token(';')
                    ) as assignop
                )
            ) as stmts
        )
    }
} >> {
    $(stmts ... {
        $(assignop ? ... {
            $(binary ? ... {
                $(binary_op ... {
                    $(binary_xor ? ... {
                        $(result) = $this->context->builder->bitwiseXor($(binary_left), $__right);
                    })
                })
            })
        })
    })
}

compile {
    $result = $value ^ 1;
}

?>
--EXPECTF--
Error unpacking a non unpackable Ast node on `$(binary_xor?... {` at line 29 with context: [
    "^"
]

Hint: use a non ellipsis expansion as in `$(binary_xor ? {`
