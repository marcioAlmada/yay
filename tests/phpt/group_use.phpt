--TEST--
A proof of concept polyfill for group use --pretty-print
--FILE--
<?php

$(macro) {
    use $(ns() as base) \ $$$ {
        $(
            ls(
                chain(
                    optional(either(const, function)) as type
                    ,
                    ns() as name
                    ,
                    optional(
                        chain(
                            as
                            ,
                            identifier() as label
                        )
                    )
                    as alias
                )
                as entry
                ,
                token(',')
            )
            as entries
        )
    }
} >> {

    $(entries ... {
        $(entry ... {
            use $(type) $(base)\$(name) $(alias ... {as $(label)});
        })
    })
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
