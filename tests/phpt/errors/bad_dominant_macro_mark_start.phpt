--TEST--
Bad dominant macro offset
--FILE--
<?php

$(macro) {
    $! foo bar
} >> {
    _
}

?>
--EXPECTF--
Bad dominant macro marker '$!' offset 0 on line 4.
