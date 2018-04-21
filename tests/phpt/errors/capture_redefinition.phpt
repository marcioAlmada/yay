--TEST--
Compile time capture redefinition
--FILE--
<?php

$(macro) {
    $(T_VARIABLE as foo) $(T_VARIABLE as foo)
} >> {
    foo
}

?>
--EXPECTF--
Redefinition of macro capture identifier 'foo' on line 4.
