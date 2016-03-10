--TEST--
This shorthand $->
--FILE--
<?php

macro ·unsafe { $ ·not(·token(T_VARIABLE))·_  ·not(·token('{'))·_ } >> { $this }

class Foo
{
    private $bar = 'bar';

    function bar() : string {
        return $->bar;
    }

    function baz() : string {
        return $->bar();
    }

    function this() : self {
        return $;
    }

    function __toString() {
        return "{$->bar}";
    }
}

// the following should not be matched by the macro

${'var'};

${"${var}"};

$$var;

?>
--EXPECTF--
<?php

class Foo
{
    private $bar = 'bar';

    function bar() : string {
        return $this->bar;
    }

    function baz() : string {
        return $this->bar();
    }

    function this() : self {
        return $this;
    }

    function __toString() {
        return "{$this->bar}";
    }
}

// the following should not be matched by the macro

${'var'};

${"${var}"};

$$var;

?>
