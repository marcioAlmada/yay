--TEST--
Extra test for expression()
--FILE--
<?php

$(macro) {
   $(expression() as someExpression);
} >> {
    ($$(stringify($(someExpression)))); // expression
}

null;

1;

1 + 1;

SomeConstant;

[];

[1, 2, 3];

function(){};

(function(){});

new class {};

(new class { function foo(){ } })->foo();

?>
--EXPECTF--
<?php

('null'); // expression


('1'); // expression


('1+1'); // expression


('SomeConstant'); // expression


('[]'); // expression


('[1,2,3]'); // expression


('function(){}'); // expression


('(function(){})'); // expression


('new class{}'); // expression


('(new class{function foo(){ } })->foo()'); // expression


?>
