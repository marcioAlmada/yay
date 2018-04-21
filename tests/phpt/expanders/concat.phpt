--TEST--
Test concat expander
--FILE--
<?php

$(macro) {
    yay\concat($(label() as word))
} >> {
    $$(concat(foo_ $(word) _baz))
}

yay\concat(bar);

?>
--EXPECTF--
<?php

foo_bar_baz;

?>
