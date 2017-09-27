--TEST--
Test for ?· operator
--FILE--
<?php

macro {
    T_STRING·foo;
}>>{
    T_STRING·foo ?· { T_STRING·undefined };
}

test;

?>
--EXPECTF--
Undefined macro expansion 'T_STRING·undefined' on line 6 with context: [
    "T_STRING·foo",
    0
]
