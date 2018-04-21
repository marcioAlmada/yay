--TEST--
Macro hygiene
--FILE--
<?php

$(macro) { test($(T_VARIABLE as A), $(T_VARIABLE as B)); } >> {

$x += $(A);
$y += $(B);

}

test($a, $b);

test($b, $c);

$(macro) { $(T_VARIABLE as A) += $(T_VARIABLE as B); } >> { $z = ($(A) += $(B)); }

test($a, $b);

$(macro) { unsafe_test($(T_VARIABLE as A)) } >> { $unsafe = $$(unsafe($code)) = $(A); }

unsafe_test($dirty);

$(macro) {

    retry ( $(layer() as times) ) { $(layer() as body) }

} >> {

    $times = (int)($(times));

    retry: {
        if ($times > 0) {
            $(body);
            $times--;
            goto retry;
        }
    }
}

retry(3) { echo "Attempt..."; }

?>
--EXPECTF--
<?php

$x___0 += $a;
$y___0 += $b;

$x___1 += $b;
$y___1 += $c;

$z___3 = ($x___2 += $a);
$z___4 = ($y___2 += $b);

$unsafe___5 = $code = $dirty;;

$times___6 = (int)(3);

    retry___6: {
        if ($times___6 > 0) {
            echo "Attempt..."; ;
            $times___6--;
            goto retry___6;
        }
    }

?>
