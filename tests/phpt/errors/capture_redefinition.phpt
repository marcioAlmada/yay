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
Redefinition of macro capture identifier 'foo', in %s.phpt on line 4.
