--TEST--
Capture tokens should always have an identifier
--FILE--
<?php

macro { foo } >> { T_VARIABLE· }

?>
--EXPECTF--
Bad macro expansion identifier 'T_VARIABLE·' on line 3.
