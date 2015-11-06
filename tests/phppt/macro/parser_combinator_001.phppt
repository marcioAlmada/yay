--TEST--
Parser combinator with "T_*·label" argument
--FILE--
<?php

macro {
    { ·ls(T_STRING·name, ·token(','))·names }
} >> {
    [·names ··· { T_STRING·name, }]
}

{ a, b, c }

?>
--EXPECTF--
<?php

[a, b, c, ]

?>
