--TEST--
Empty expansion and comments
--FILE--
<?php

$(macro) { @ $(T_STRING as label) ; } >> { };

@foo;

@ /**/ bar /**/;

@
    baz
            ;

?>
--EXPECTF--
<?php







?>
