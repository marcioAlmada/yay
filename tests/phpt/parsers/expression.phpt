--TEST--
Extra test for ·expression()
--FILE--
<?php

macro {
   ·expression()·someExpression
} >> {
    (··stringify(·someExpression)); // expression
}

1 + 1

?>
--EXPECTF--
<?php

('1+1'); // expression


?>
