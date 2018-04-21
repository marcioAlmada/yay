--TEST--
Test for ! operator
--FILE--
<?php

$(macro) {
    $(T_STRING as foo);
} >> {
    $(bar ! {$(bar)});
}

test;

?>
--EXPECTF--
Undefined macro expansion 'bar' on line 6 with context: [
    "foo",
    0
]
