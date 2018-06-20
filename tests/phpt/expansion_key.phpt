--TEST--
Expansion --pretty-print
--FILE--
<?php

$(macro) {
    enum $(label() as name) { $(lst(label() as property, token(',')) as properties) }
} >> {
    const
        $(properties ...(,) i {
            Sort_$(property) = $(i)
        })
    ;
}

class Collection
{
    protected enum Sort {
        Normal,
        Key,
        Assoc,
    }
}

?>
--EXPECTF--
<?php

class Collection
{
    protected const Sort_Normal = 0, Sort_Key = 1, Sort_Assoc = 2;
}

?>