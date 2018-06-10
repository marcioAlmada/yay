--TEST--
Capture tokens should always have a type
--FILE--
<?php

$(macro) { $(foo) } >> { $(foo) }

?>
--EXPECTF--
Bad macro capture identifier '$(foo)' on line 3.
