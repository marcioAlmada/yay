--TEST--
Malformed macro
--FILE--
<?php

$(macro) { x } // bad macro, missing expansion section

?>
--EXPECTF--
Unexpected T_CLOSE_TAG(?>), in %s.phpt on line 5, expected T_SR().
