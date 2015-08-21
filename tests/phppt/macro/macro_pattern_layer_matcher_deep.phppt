--TEST--
Non delimited layer matching with nesting
--FILE--
<?php

macro {
    (T_STRING·A (T_STRING·B ···rest))
} >> {
    (T_STRING·B ···rest)
}

(level_a (level_b [level_c, 1, 2, 3]))

// done

macro {
    (T_STRING·A (T_STRING·B (T_STRING·C ···rest)))
} >> {
    (T_STRING·C ···rest)
}

(level_a (level_b (level_c [level_d, 1, 2, 3, { level_e : (4) }])))

?>
--EXPECTF--
<?php

(level_b [level_c, 1, 2, 3])

// done

(level_c [level_d, 1, 2, 3, { level_e : (4) }])

?>
