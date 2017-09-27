--TEST--
Test for !· operator
--FILE--
<?php

macro {
    T_STRING·foo;
} >> {
    ·bar !· {pass(T_STRING·foo)};
}

test;

?>
--EXPECTF--
<?php

pass(test);

?>
