--TEST--
Provides an AST to a custom expander
--FILE--
<?php

$(macro) {
    $(
        chain(
            chain(
                buffer("foo")
            ) as inner
        ) as outer
    )
} >> {
    $$(\Yay\tests\fixtures\expanders\wrap_ast_expander(
        $$(\Yay\tests\fixtures\expanders\upper_ast_expander(
            $$(\Yay\tests\fixtures\expanders\reverse_ast_expander(
                $(outer)
            ))
        ))
    ))
}

foo

?>
--EXPECTF--
<?php

[OOF]

?>
