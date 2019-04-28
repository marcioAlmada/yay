--TEST--
Non delimited layer matching 
--FILE--
<?php

$(macro) {
    µ$((...) as match)
} >> {
    MATCH
}

µ((() // pairs don't match

?>
--EXPECTF--
Unexpected end at T_CLOSE_TAG(?>), in %s.phpt on line 11, expected ')'.
