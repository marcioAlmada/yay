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
Bad dominant macro marker '$!' offset 0, in %s.phpt on line 4.
