--TEST--
Disallow simple infinite macro recursion (constant macros)
--FILE--
<?php

macro { FOO } >> { FOO(FOO) : FOO() };

FOO;

macro { BAR } >> { BAR BAR(BAR) };

BAR;

macro { C } >> { A }
macro { A } >> { B }
macro { B } >> { C }

B;

?>
--EXPECTF--
<?php

FOO(FOO) : FOO();

BAR BAR(BAR);

B;

?>
