--TEST--
Cloaking (necessary for certain kinds of second order macros)
--FILE--
<?php

macro { foo } >> { \\(·notAnExpander(foo bar baz)) }

foo;

macro { \\( bar ) } >> { \\(·notAnExpander(foo bar baz)) }

bar;

?>
--EXPECTF--
<?php

·notAnExpander(foo bar baz);

·notAnExpander(foo bar baz);

?>
