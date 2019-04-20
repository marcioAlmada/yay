--TEST--
Test expander call with missing argument
--FILE--
<?php

$(macro) {
    match($(layer() as args))
} >> {
    $$(stringify(/* forgotten $(args) /o\ */))
}

match(foo);

?>
--EXPECTF--

TokenStream expander called without tokens `$$(stringify())` as function Yay\DSL\Expanders\stringify(Yay\TokenStream $ts) on line 6
