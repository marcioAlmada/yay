--TEST--
Capture tokens should always have a type
--FILE--
<?php

$(macro) { $(foo) } >> { $(foo) }

?>
--EXPECTF--
Bad macro capture identifier '$(foo)', in %s.phpt on line 3.
