--TEST--
Malformed macro, empty expansion
--FILE--
<?php

macro {

} >> {
    x
}

?>
--EXPECTF--
Empty macro pattern on line 3.
