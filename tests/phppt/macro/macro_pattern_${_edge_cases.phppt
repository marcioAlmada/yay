--TEST--
Edge cases with '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_STRING_VARNAME
--FILE--
<?php

macro { "Foo { literal string }" } >> { ok }
macro { "Foo {$x->T_STRING·member}" } >> { ok T_STRING·member }
macro { "Foo {$x->T_STRING·member()}" } >> { ok T_STRING·member }
macro { "Foo ${T_STRING_VARNAME·name}" } >> { ok T_STRING_VARNAME·name }

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
