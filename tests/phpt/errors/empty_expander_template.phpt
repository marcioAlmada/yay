--TEST--
Empty expander template
--FILE--
<?php

macro {
    ·token(T_STRING)·foo
} >> {
    ··concat() // forgot to pass ·foo
}

SOME_T_STRING;

?>
--EXPECTF--
Empty expander slice on '··concat()' at line 6.
