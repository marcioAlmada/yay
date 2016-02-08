--TEST--
Capture tokens should always have an identifier
--FILE--
<?php

macro { T_VARIABLE· } >> { foo }

?>
--EXPECTF--
Bad macro capture identifier 'T_VARIABLE·' on line 3.
