--TEST--
Uses a custom fully qualified parser function --pretty-print
--FILE--
<?php

$(macro) {
    test($(\Yay\tests\fixtures\parsers\my_custom_parser() as matched))
} >> {
    test: $$(stringify($(matched)));
}

$(macro) {
    test_non_aliased_ast($(\Yay\tests\fixtures\parsers\my_custom_argument_parser()))
} >> {
    test: $(argument... {
            $$(stringify($(argument[type][nullable])$(argument[type][name]) $(argument[argument_name])));
            $(type[nullable] ?{ 'Argument is nullable' });
            $(type[nullable] !{ 'Argument is not nullable' });
            $$(stringify(Argument type is $(type[name])));
            $$(stringify(Argument name is $(argument_name)));
        })
}

$(macro) {
    test_aliased_ast($(\Yay\tests\fixtures\parsers\my_custom_argument_parser() as alias))
} >> {
    test: $(alias... {
            $$(stringify($(alias[type][nullable])$(alias[type][name]) $(alias[argument_name])));
            $(type[nullable] ?{ 'Argument is nullable' });
            $(type[nullable] !{ 'Argument is not nullable' });
            $$(stringify(Argument type is $(type[name])));
            $$(stringify(Argument name is $(argument_name)));
        })
}

$(macro) {
    $(\Custom\parser())
} >> {
    $(alias ? {
        "replaced"
    })
}

test(foo);
test_aliased_ast(?string $x);
test_non_aliased_ast(int $y);
found;

?>
--EXPECTF--
<?php

test___0:
'foo';
test___1:
'?string $x';
'Argument is nullable';
'Argument type is string';
'Argument name is $x';
test___2:
'int $y';
'Argument is not nullable';
'Argument type is int';
'Argument name is $y';
"replaced";

?>
