--TEST--
Test for !· operator
--FILE--
<?php

macro {
    T_STRING·foo;
} >> {
    T_STRING·bar !· {T_STRING·bar};
}

test;

?>
--EXPECTF--
Undefined macro expansion 'T_STRING·bar' on line 6 with context: [
    "T_STRING·foo",
    0
]
