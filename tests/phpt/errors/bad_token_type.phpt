--TEST--
Bad token types
--FILE--
<?php

$(macro) { $(T_HAKUNAMATATA as foo) } >> { };

?>
--EXPECTF--
Undefined token type 'T_HAKUNAMATATA' on line 3.
