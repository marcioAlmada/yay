--TEST--
Test for ? operator
--FILE--
<?php

$(macro) {
    $(T_STRING as foo);
}>>{
    $(undefined ? { pass($(foo)) });
}

test;

?>
--EXPECTF--
<?php

;

?>
