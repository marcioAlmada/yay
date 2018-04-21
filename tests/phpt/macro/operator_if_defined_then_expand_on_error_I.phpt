--TEST--
Test for ? operator
--FILE--
<?php

$(macro) {
    $(T_STRING as foo);
}>>{
    $(foo ? { $(undefined) });
}

test;

?>
--EXPECTF--
Undefined macro expansion 'undefined' on line 6 with context: [
    "foo",
    0
]
