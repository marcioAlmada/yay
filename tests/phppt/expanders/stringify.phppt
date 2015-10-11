--TEST--
Test ·stringify expander
--FILE--
<?php

macro {
    yay\stringify(···args)
} >> {
    ·stringify(···args)
}

$source = yay\stringify(function($a, $b $c){ echo 'the sum is: ' . $a + $b + $c; });

?>
--EXPECTF--
<?php

$source = 'function($a, $b $c){ echo \'the sum is: \' . $a + $b + $c; }';

?>
