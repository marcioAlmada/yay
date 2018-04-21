--TEST--
Empty expansions
--FILE--
<?php

$(macro) { $foo->bar->baz; } >> { };

$(macro) { DEBUG { $(layer() as body) } } >> { };

$foo->bar;

$foo->bar->baz; // match

$foo->/**/bar->/**/baz; // match

DEBUG {
    log('debug!');
}

DEBUG();

?>
--EXPECTF--
<?php

$foo->bar;

 // match

 // match



DEBUG();

?>
