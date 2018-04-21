--TEST--
Parser combinator with type and alias. Ex.: "token(T_STRING) as name"
--FILE--
<?php

$(macro) {
    { $(ls(token(T_STRING) as name, token(',')) as names) }
} >> {
    [$(names ...(, ) {$(name)})]
}

{ a, b, c }

?>
--EXPECTF--
<?php

[a, b, c]

?>
