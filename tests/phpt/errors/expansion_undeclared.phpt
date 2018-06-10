--TEST--
Expansion tokens should always have a valid reference
--FILE--
<?php


$(macro) {
    $(T_VARIABLE as foo) >> $(T_VARIABLE as bar)
} >> {
    $(foo) $(bar) $(baz)
           		  // ^ undefined expansion!!!
}


$a >> $b;

?>
--EXPECTF--
Undefined macro expansion 'baz' on line 7 with context: [
    "foo",
    "bar"
]
