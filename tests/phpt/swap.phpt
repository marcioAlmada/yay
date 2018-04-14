--TEST--
Swap
--FILE--
<?php

$(macro) {
    swap ( $(T_VARIABLE as A) , $(T_VARIABLE as B) )
} >> {
    (list($(A), $(B)) = [$(B), $(A)])
}

$x = 1;
$y = 0;

swap($x, $y);

var_dump($x, $y);

swap
        (
    $x,
        $y
);

var_dump($x, $y);

?>
--EXPECTF--
<?php

$x = 1;
$y = 0;

(list($x, $y) = [$y, $x]);

var_dump($x, $y);

(list($x, $y) = [$y, $x]);

var_dump($x, $y);

?>
