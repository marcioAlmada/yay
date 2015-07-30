--TEST--
Compile time capture redefinition
--FILE--
<?php

macro {
    T_VARIABLE·foo T_VARIABLE·foo
} >> {
    foo
}

?>
--EXPECTF--
Redefinition of macro capture identifier 'T_VARIABLE·foo' on line 4.
