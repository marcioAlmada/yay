--TEST--
test for ·expr() T_LNUMBER
--FILE--
<?php

macro {
   ·expr()·my_expr
} >> {
expression {
    ·my_expr
}
}

42

?>
--EXPECTF--
<?php

expression {
    42
}

?>