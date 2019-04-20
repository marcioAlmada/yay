--TEST--
Only evaluates custom expanders when required --pretty-print
--FILE--
<?php


$(macro) {
    $(
        repeat(
            either(
                buffer("foo") as fooMatch,
                buffer("bar") as barMatch
            )
        ) as matches
    )
} >> {
    $(matches ... {
        $(fooMatch ? {
            $$(\Yay\tests\fixtures\expanders\my_foo_expander($(fooMatch)));
        })

        $(barMatch ? {
            $$(\Yay\tests\fixtures\expanders\my_bar_expander($(barMatch)));
        })
    })
}

foo bar foo

?>
--EXPECTF--
<?php

'called Yay\\tests\\fixtures\\expanders\\my_foo_expander(foo)';
'called Yay\\tests\\fixtures\\expanders\\my_bar_expander(bar)';
'called Yay\\tests\\fixtures\\expanders\\my_foo_expander(foo)';

?>
