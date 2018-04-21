--TEST--
Non delimited layer matching with nesting
--FILE--
<?php

$(macro) {
    ($(T_STRING as A) ($(T_STRING as B) $(... as rest)))
} >> {
    ($(B) $(rest))
}

(level_a (level_b [level_c, 1, 2, 3]))

// done

$(macro) {
    ($(T_STRING as A) ($(T_STRING as B) ($(T_STRING as C) $(... as rest))))
} >> {
    ($(C) $(rest))
}

(level_a (level_b (level_c [level_d, 1, 2, 3, { level_e : (4) }])))

?>
--EXPECTF--
<?php

(level_b [level_c, 1, 2, 3])

// done

(level_c [level_d, 1, 2, 3, { level_e : (4) }])

?>
