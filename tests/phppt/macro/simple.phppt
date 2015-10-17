--TEST--
Bug
--FILE--
<?php

macro {
    T_VARIABLE·A(T_VARIABLE·B)
} >> {
    T_VARIABLE·A T_VARIABLE·B
}

$x($y);

?>
--EXPECTF--
<?php

$x $y;

?>
