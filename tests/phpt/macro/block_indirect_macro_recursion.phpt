--TEST--
Detect indirect infine macro recursion
--FILE--
<?php

$(macro) { A } >> { B A };
$(macro) { B } >> { B C };
$(macro) { C } >> { C A };

A;

?>
--EXPECTF--
<?php

B C A A;

?>
