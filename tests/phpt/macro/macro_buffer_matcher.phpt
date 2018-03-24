--TEST--
Test ·buffer
--FILE--
<?php

macro {
    T_VARIABLE·X ·buffer('<(o . o)>') T_VARIABLE·Y
} >> {
    (T_VARIABLE·X ."hug". T_VARIABLE·Y)
}

$foo <(o.o)> $bar;
     // ^ this is not a hug!

$a <(o . o)> $b;

$b<(o . o)>$c;

$c<(o . o)> $d;

$d<(o . o)> $e;

$e
<(o . o)>
$f;

    $f
    <(o . o)>
        $g;

$foo < (o.o) > $bar;
     // ^ this is not a hug!

$e
<(o . o)> /**/
$f;

?>
--EXPECTF--
<?php

$foo <(o.o)> $bar;
     // ^ this is not a hug!

($a ."hug". $b);

($b ."hug". $c);

($c ."hug". $d);

($d ."hug". $e);

($e ."hug". $f);

    ($f ."hug". $g);

$foo < (o.o) > $bar;
     // ^ this is not a hug!

($e ."hug". $f);

?>
