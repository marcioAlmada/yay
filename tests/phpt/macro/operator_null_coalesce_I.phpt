--TEST--
Test for ?! operator --pretty-print
--FILE--
<?php

$(macro :global) {
    type $(T_STRING as handler) $(optional(chain(token(T_EXTENDS), indentation(), ns())) as extended)
}
>> {
    class $(handler) $(extended ?! {extends \StandardType})
}

type Foo
{
}
type Bar extends Foo
{
}

?>
--EXPECTF--
<?php

class Foo extends \StandardType
{
}
class Bar extends Foo
{
}

?>

