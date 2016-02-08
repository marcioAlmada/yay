--TEST--
Test ast unpacking (···) with trailing delimiter
--FILE--
<?php

macro {
    { ·ls(·token(T_STRING)·label, ·token(':'))·list }
} >> {
    [ ·list ···(, ){·label}, ]
}

{ A: B: C: D };

?>
--EXPECTF--
<?php

[ A, B, C, D, ];

?>
