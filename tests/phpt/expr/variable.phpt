--TEST--
test for ·expr() array of T_LNUMBER
--FILE--
<?php

macro {
   ·expr()·my_expr
} >> {
expression {
    ·my_expr
}
}

$a

?>
--EXPECTF--
<?php

expression {
    $a
}

?>