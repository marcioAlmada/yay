--TEST--
Opaque types with macros that generate other macros :>
--FILE--
<?php

$(macro) {
    type $(T_STRING as newtype) = $(T_STRING as newtype);
} >> {
    macro \\(·optimize) {
        \\(·either(instanceof, ·token(','), ·token('('))·prec) T_STRING·newtype
    } >> {
        \\(·prec) T_STRING·basetype
    }
}

type Username = string;
type Password = string;

function register_user(Username $nick, Password $password ) : User {}
function register_user(\Username $nick, \Password $password ) : User {}

?>
--EXPECTF--
<?php

function register_user( string $nick, string $password ) : User {}
function register_user(\Username $nick, \Password $password ) : User {}

?>
