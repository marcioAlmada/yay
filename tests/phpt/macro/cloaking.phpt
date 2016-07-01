--TEST--
Cloaking (necessary for certain kinds of second order macros)
--FILE--
<?php

macro { foo } >> { \\(·notAnExpander(first)) }

foo;

macro { \\( bar ) } >> { \\(·notAnExpander(second)) }

bar;

?>
--EXPECTF--
<?php

·notAnExpander(first);

bar;

?>
