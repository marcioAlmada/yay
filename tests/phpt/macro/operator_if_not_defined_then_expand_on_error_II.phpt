--TEST--
Test for ! operator
--FILE--
<?php

$(macro) {
    $(T_STRING as foo);
} >> {
    $(bar ! {pass($(foo))});
}

test;

?>
--EXPECTF--
<?php

pass(test);

?>
