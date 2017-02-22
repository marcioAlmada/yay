--TEST--
A proof of concept polyfill for group use --pretty-print
--FILE--
<?php

macro ·grammar {

    ·entries { list(·entry , '','') }
    ·entry { ·type ·namespace{}·name ·alias }
    ·type ?{ ''const'' | ''function'' }
    ·alias ?{ ''as'' ·identifier()·label }
    ·namespace { ·ns() }

    << ·group_use { ''use'' ·namespace{}·base ''\'' ''{'' ·entries !! ''}'' }

} >> {
    ·group_use ··· {
        ·entries ··· {
            ·entry ··· {
                use ·type ·base\·name ·alias ···{as ·label};
            }
        }
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
