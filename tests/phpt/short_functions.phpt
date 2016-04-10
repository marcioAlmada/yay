--TEST--
Shorthand function with support for:
    [x] lexical scoping
    [x] return types
    [x] argument types
    [ ] default argument values // requires ·constantExpression() parser

use --pretty-print

--FILE--
<?php

macro ·recursion {
    fn
    (
        ·ls
        (
            ·chain
            (
                ·optional(·ns()·type)
                ,
                ·token(T_VARIABLE)·arg_name
            )
            ·arg
            ,
            ·token(',')
        )
        ·args
    )

    ·optional
    (
        ·chain
        (
            ·token(':')
            ,
            ·ns()
        )
    )
    ·return_type
    ~>
    (···body)
} >> {
    (function ($context){
        return function (·args ···(, ){ ·arg ···{·type ·arg_name}}) use($context) ·return_type {
            extract($context);
            return ···body;
        };
    })(get_defined_vars())
}

$y = 100;

array_map(fn (int $x):int ~> ($x * 2 * $y), range(0, 10));

?>
--EXPECTF--
<?php

$y = 100;
array_map((function ($context·0) {
    return function (int $x) use($context·0) : int {
        extract($context·0);
        return $x * 2 * $y;
    };
})(get_defined_vars()), range(0, 10));

?>
