--TEST--
Test for ? operator
--FILE--
<?php

$(macro) {
    $(T_STRING as foo);
}>>{
    $(foo ? {pass($(foo))});
}

test;

?>
--EXPECTF--
<?php

pass(test);

?>
