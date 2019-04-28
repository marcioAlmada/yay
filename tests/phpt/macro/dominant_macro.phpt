--TEST--
Dominant macro
--FILE--
<?php

$(macro) { entry_point_a { $(T_STRING as A) => $(T_STRING as B) } } >> { "works" }

entry_point_a { A => B => C } // backtracks at second "=>" and ignores

$(macro) { entry_point_b $! { $(T_STRING as A) => $(T_STRING as B) } } >> { "works" }

entry_point_b { X => Y -> Z } // fails at "->"

?>
--EXPECTF--

Unexpected T_OBJECT_OPERATOR(->), in %s.phpt on line 9, expected '}'.
