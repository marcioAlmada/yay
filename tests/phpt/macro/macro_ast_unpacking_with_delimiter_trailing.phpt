--TEST--
Test ast unpacking with trailing delimiter
--FILE--
<?php

$(macro) {
    { $(ls(token(T_STRING) as label, token(':')) as list)}
} >> {
    [ $(list ...(, ){$(label)}), ]
}

{ A: B: C: D };

?>
--EXPECTF--
<?php

[ A, B, C, D, ];

?>
