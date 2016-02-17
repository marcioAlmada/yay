--TEST--
A proof of concept polyfill for group use --pretty-print
--FILE--
<?php

macro {
    use ·ns()·base \ · {
        ·ls(
            ·chain(
                ·optional(·either(const, function))·type
                ,
                ·ns()·entry
                ,
                ·optional(
                    ·chain(
                        as
                        ,
                        ·identifier()·label
                    )
                )
                ·alias
            ),
            ·token(',')
        )
        ·entries
    }
} >> {

·entries ··· {
    use ·type ·base\·entry ·alias ··· {as ·label};
}

}

use A\B\C\{
    Foo,
    Foo\Bar,
    Baz as Boo,
    const X as Y,
    function d\e as f
}

?>
--EXPECTF--
<?php

use A\B\C\Foo;
use A\B\C\Foo\Bar;
use A\B\C\Baz as Boo;
use const A\B\C\X as Y;
use function A\B\C\d\e as f;

?>
