--TEST--
Test ast unpacking with optional flag
--FILE--
<?php

$(macro) {
    $(T_STRING as foo);
} >> {
    $(undefined ?... { ($(item)) });
}

foo;

?>
--EXPECTF--
<?php

;

?>
