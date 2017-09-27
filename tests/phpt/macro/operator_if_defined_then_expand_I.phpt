--TEST--
Test for ?· operator
--FILE--
<?php

macro {
    T_STRING·foo;
}>>{
    T_STRING·foo ?· {pass(T_STRING·foo)};
}

test;

?>
--EXPECTF--
<?php

pass(test);

?>
