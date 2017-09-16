--TEST--
test for ·expr() D_LNUMBER
--FILE--
<?php

macro {
   ·expr()·my_expr
} >> {
expression {
    ·my_expr
}
}

42.0

?>
--EXPECTF--
<?php

expression {
    42.0
}

?>