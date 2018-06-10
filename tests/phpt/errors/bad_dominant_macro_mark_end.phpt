--TEST--
Bad dominant macro offset
--FILE--
<?php

$(macro) {
    foo bar $!
} >> {
    _
}

?>
--EXPECTF--
Bad dominant macro marker '$!' offset 2 on line 4.
