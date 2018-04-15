--TEST--
Opaque types with macros that generate other macros :>
--FILE--
<?php

$(macro) {
	// this macro creates another macro that when run replaces the literal
	// carried by $(basetype) with the literal carried by $(newtype)
    type $(T_STRING as basetype) = $(T_STRING as newtype);
} >> {
    \\$(macro) {
        \\$(either(instanceof, token(','), token('(')) as prec) \\$(optional(indentation()) as whitespace) $(basetype)
    } >> {
        \\$(prec)\\$(whitespace)$(newtype)
    }
}

type Username = string;
type Password = string;

function register_user(Username $nick, Password $password ) : User {}
function register_user(\Username $nick, \Password $password ) : User {}

?>
--EXPECTF--
<?php

function register_user(string $nick, string $password ) : User {}
function register_user(\Username $nick, \Password $password ) : User {}

?>
