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
Bad macro expander 'undefined', in %s.phpt on line 6.
