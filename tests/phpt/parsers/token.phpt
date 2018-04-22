--TEST--
Extra test for `token()` and `$(T_TOKEN_NAME as label)`
--FILE--
<?php

$(macro) {
    $(T_STRING as foo) $(token(T_STRING) as bar) $(chain(T_STRING as baz, token(T_STRING) as buz) as ast)
} >> {
    $$(stringify($(foo)_$(bar)_$(ast ... {$(baz)_$(buz)})))
}

a b c d;

?>
--EXPECTF--
<?php

'a_b_c_d';

?>
