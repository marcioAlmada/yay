--TEST--
Uses a custom fully qualified expansion function
--FILE--
<?php

$(macro) {
    hello($(token(T_STRING) as matched))
} >> {
    $$(\Yay\tests\fixtures\expanders\my_hello_expander($(matched)))
}

hello(Chris);

?>
--EXPECTF--
<?php

'Hello, Chris';

?>
