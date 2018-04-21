--TEST--
Test ast unpacking without optional flag
--FILE--
<?php

$(macro) {
    $(T_STRING as foo)(
        $(optional(ls(token(T_STRING) as item, token(','))) as list)
    );
} >> {
    $(foo)($(list ... { ($(item))}));
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
