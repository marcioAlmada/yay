--TEST--
test variables and operators expressions
--FILE--
<?php

macro {
   ·expr()·my_expr
} >> {
expression {
    ·my_expr
}
}

$a instanceof \Some\Large\FQCN

?>
--EXPECTF--
<?php

expression {
    $ainstanceof\Some\Large\FQCN
}

?>