--TEST--
Layer matcher simple test --pretty-print
--FILE--
<?php

$(macro) {
    match $({...} as bar)
} >> {
    $$(stringify($(bar)))
}

match {this is inside a layer { and this is too } }

?>
--EXPECTF--
<?php

'this is inside a layer { and this is too } ';

?>
