--TEST--
Uses a custom fully qualified parser function with default AST label declared internally
--FILE--
<?php

$(macro) {
    $(chain(
        \Yay\tests\fixtures\parsers\my_custom_parser_with_default_alias() as new_alias
    ))
}
>> {
    ok($$(stringify($(new_alias))));
}

foo

?>
--EXPECTF--
<?php

ok('foo');

?>
