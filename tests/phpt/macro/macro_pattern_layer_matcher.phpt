--TEST--
Non delimited layer matching 
--FILE--
<?php

$(macro :recursion) {
    ($(T_STRING as A) $(... as rest)) // matches a lisp form
} >> {
    $(A)($(rest))
}

(sum 1 (multiply 2 3))

?>
--EXPECTF--
<?php

sum(1 multiply(2 3))

?>
