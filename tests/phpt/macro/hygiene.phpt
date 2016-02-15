--TEST--
Macro hygiene
--FILE--
<?php

macro { test(T_VARIABLE·A, T_VARIABLE·B); } >> {

$x += T_VARIABLE·A;
$y += T_VARIABLE·B;

}

test($a, $b);

test($b, $c);

macro { T_VARIABLE·A += T_VARIABLE·B; } >> { $z = (T_VARIABLE·A += T_VARIABLE·B); }

test($a, $b);

macro { unsafe_test(T_VARIABLE·A) } >> { $unsafe = ··unsafe($code) = T_VARIABLE·A; }

unsafe_test($dirty);

macro {

    retry ( ···times ) { ···body }

} >> {

    $times = (int)(···times);

    retry: {
        if ($times > 0) {
            ···body;
            $times--;
            goto retry;
        }
    }
}

retry(3) { echo "Attempt..."; }

?>
--EXPECTF--
<?php

$x·0 += $a;
$y·0 += $b;

$x·1 += $b;
$y·1 += $c;

$z·3 = ($x·2 += $a);
$z·4 = ($y·2 += $b);

$unsafe·5 = $code = $dirty;;

$times·6 = (int)(3);

    retry·6: {
        if ($times·6 > 0) {
            echo "Attempt..."; ;
            $times·6--;
            goto retry·6;
        }
    }

?>
