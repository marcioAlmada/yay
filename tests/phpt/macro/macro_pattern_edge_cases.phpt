--TEST--
Edge cases with '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_STRING_VARNAME
--FILE--
<?php

$(macro) { "Foo { literal string }" } >> { ok }
$(macro) { "Foo {$x->$(T_STRING as member)}" } >> { ok $(member) }
$(macro) { "Foo {$x->$(T_STRING as member)()}" } >> { ok $(member) }
$(macro) { "Foo ${$(T_STRING_VARNAME as name)}" } >> { ok $(name) }

"Foo { literal string }";
"Foo {$x->y}";
"Foo {$x->y()}";
"Foo ${x}";

?>
--EXPECTF--
<?php

ok;
ok y;
ok y;
ok x;

?>
