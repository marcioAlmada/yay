--TEST--
Empty expansions
--FILE--
HTML here, this should be preserved: @ "debug" public

<?php

macro { x } >> { }
macro { @ } >> { }
macro { public } >> { }
macro { "debug" } >> { } // this comment should be preserved

@test("debug");

class X {
    public function test(){}
}

?>
--EXPECTF--
HTML here, this should be preserved: @ "debug" public

<?php

// this comment should be preserved

test();

class X {
    function test(){}
}

?>
