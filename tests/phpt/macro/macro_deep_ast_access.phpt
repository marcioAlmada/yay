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
    matched($(level_a[leaf_level_a]));
    matched($(level_a[level_b][leaf_level_b]));
    matched($(level_a[level_b][level_c][leaf_level_c]));
    // equivalent to:
    matched($(level_a ... { $(leaf_level_a) }));
    matched($(level_a ... { $(level_b ... { $(leaf_level_b) }) }));
    matched($(level_a ... { $(level_b ... { $(level_c ... { $(leaf_level_c) })}) }));
}

match {
    leaf_level_a
        leaf_level_b
            leaf_level_c
}

?>
--EXPECTF--
<?php

matched(leaf_level_a);
matched(leaf_level_b);
matched(leaf_level_c);
// equivalent to:
matched(leaf_level_a);
matched(leaf_level_b);
matched(leaf_level_c);

?>
