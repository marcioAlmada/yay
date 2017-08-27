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

clone $a

?>
--EXPECTF--
<?php

expression {
    clone$a
}

?>