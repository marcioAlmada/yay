--TEST--
Proof of concept "unless" implementation
--FILE--
<?php

macro {
    unless · (···expression) { ···body }
} >> {
    if (! (···expression)) {
        ···body
    }
}

unless ($x === 1) {
    echo "\$x is not 1";
}

?>
--EXPECTF--
<?php

if (! ($x === 1)) {
        echo "\$x is not 1";

    }

?>
