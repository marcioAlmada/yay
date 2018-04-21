--TEST--
Cloaking (necessary for certain kinds of second order macros)
--FILE--
<?php

$(macro) { foo } >> { \\$$(notAnExpander(first)) }

$(macro) { bar } >> { \\$(notAnExpansion) }

foo;

bar;

$(macro) { $ ( baz ) } >> { \\$$(notAnExpander(second)) }

baz;

?>
--EXPECTF--
<?php

$$(notAnExpander(first));

$(notAnExpansion);

baz;

?>
