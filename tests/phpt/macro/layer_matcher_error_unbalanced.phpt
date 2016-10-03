--TEST--
Layer matcher error with unbalanced token pairs
--FILE--
<?php

macro {
    µ(···match)
} >> {
    MATCH
}

µ(foo, {bar, [baz}]); // pairs don't match

?>
--EXPECTF--
Unexpected '}' on line 9, expected ']'.
