--TEST--
Test for ??· operator --pretty-print
--FILE--
<?php

macro ·global {
    type T_STRING·handler ·optional(·chain(·token(T_EXTENDS), ·indentation(), ·ns()))·extended
}
>> {
    class T_STRING·handler ·undefined ?!· {extends \StandardType}
}

type Foo
{
}
type Bar extends Foo
{
}

?>
--EXPECTF--
<?php

class Foo extends \StandardType
{
}
class Bar extends \StandardType
{
}

?>

