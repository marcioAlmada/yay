--TEST--
Test macro $(deep[ast][access]) syntax --pretty-print
--FILE--
<?php

$(macro) {
    match
    {
        $(
            chain
            (
                token(T_STRING) as leaf_level_a
                ,
                chain
                (
                    token(T_STRING) as leaf_level_b
                    ,
                    chain
                    (
                        token(T_STRING) as leaf_level_c
                    )
                    as level_c
                )
                as level_b
            )
            as level_a
        )
    }
} >> {
    matched($(level_a[level_b][level_c][leaf_level_x]));
}

match {
    leaf_level_a
        leaf_level_b
            leaf_level_c
}

?>
--EXPECTF--
Undefined macro expansion 'level_a[level_b][level_c][leaf_level_x]', in %s.phpt on line 27 with context: [
    "level_a"
]
