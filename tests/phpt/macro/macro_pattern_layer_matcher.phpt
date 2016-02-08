--TEST--
Non delimited layer matching 
--FILE--
<?php

macro ·recursion {
    (T_STRING·A ···rest) // matches a lisp form
} >> {
    T_STRING·A(···rest)
}

(sum 1 (multiply 2 3))

?>
--EXPECTF--
<?php

sum(1 multiply(2 3))

?>
