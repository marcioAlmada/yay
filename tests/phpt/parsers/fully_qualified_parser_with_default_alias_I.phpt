--TEST--
Uses a custom fully qualified parser function with default AST label declared internally
--FILE--
<?php

$(macro) {
    $(chain(
        \Yay\tests\fixtures\parsers\my_custom_parser_with_default_alias()
    ))
}
>> {
    ok($$(stringify($(default_alias_from_custom_parser))));
}

foo

?>
--EXPECTF--
<?php

ok('foo');

?>
