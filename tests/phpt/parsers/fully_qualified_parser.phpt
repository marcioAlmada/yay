--TEST--
Uses a custom fully qualified parser function
--FILE--
<?php

$(macro) {
    test($(\Yay\tests\fixtures\parsers\my_custom_parser() as matched))
} >> {
    ok($$(stringify($(matched))))
}

test(foo);

?>
--EXPECTF--
<?php

ok('foo');

?>
