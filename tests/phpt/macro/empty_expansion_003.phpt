--TEST--
Empty expansion and comments
--FILE--
<?php

macro { @ T_STRING·label ; } >> { };

@foo;

@ /**/ bar /**/;

@
    baz
            ;

?>
--EXPECTF--
<?php







?>
