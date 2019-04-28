--TEST--
Malformed macro, empty expansion
--FILE--
<?php

$(macro) {

} >> {
    x
}

?>
--EXPECTF--
Empty macro pattern, in %s.phpt on line 3.
