--TEST--
Malformed macro
--FILE--
<?php

macro { x } // bad macro, missing expansion section

?>
--EXPECTF--
Unexpected T_CLOSE_TAG(?>) on line 5, expected OPERATOR(>>).
