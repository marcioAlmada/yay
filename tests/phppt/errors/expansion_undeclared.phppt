--TEST--
Expansion tokens should always have a valid reference
--FILE--
<?php


macro {
    T_VARIABLE·foo >> T_VARIABLE·bar
} >> {
    T_VARIABLE·foo T_STRING·bar
                    // ^ undefined expansion!!!
}


$a >> $b;

?>
--EXPECTF--
Undefined macro expansion 'T_STRING·bar' on line 7 with context: [
    "T_VARIABLE·foo",
    "T_VARIABLE·bar"
]
