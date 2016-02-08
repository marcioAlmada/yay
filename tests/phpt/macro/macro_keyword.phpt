--TEST--
Macro should not become a reserved keyword
--FILE--
<?php

macro { x } >> { y }

function macro(){}

macro();

x();

?>
--EXPECTF--
<?php

function macro(){}

macro();

y();

?>
