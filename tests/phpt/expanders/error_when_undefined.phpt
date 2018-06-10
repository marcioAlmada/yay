--TEST--
Test non existant expander
--FILE--
<?php

$(macro) {
    yay\undefined($(layer() as args))
} >> {
    $$(undefined($(args)))
}

yay\undefined(...);

?>
--EXPECTF--
Bad macro expander 'undefined' on line 6.
