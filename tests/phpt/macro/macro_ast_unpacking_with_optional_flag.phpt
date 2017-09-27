--TEST--
Test ast unpacking with optional flag
--FILE--
<?php

macro {
    T_STRING·foo;
} >> {
    ·undefined ?··· { (·item) };
}

foo;

?>
--EXPECTF--
<?php

;

?>
