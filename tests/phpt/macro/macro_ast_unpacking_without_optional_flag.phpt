--TEST--
Test ast unpacking without optional flag
--FILE--
<?php

macro {
    T_STRING·foo(
        ·optional(·ls(·token(T_STRING)·item, ·token(',')))·list
    );
} >> {
    T_STRING·foo(·list ··· { (·item)});
}

foo();

bar(a, b, c);

baz(a);

bus();

?>
--EXPECTF--
<?php

foo();

bar((a)(b)(c));

baz((a));

bus();

?>
