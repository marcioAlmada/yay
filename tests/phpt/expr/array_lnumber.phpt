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

[1, 2, 3]

?>
--EXPECTF--
<?php

expression {
    [1, 2, 3]
}

?>