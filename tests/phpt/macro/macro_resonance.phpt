--TEST--
Macros should resonate according to declaration order
--FILE--
<?php

$(macro) { x ( ) } >> { y() }
$(macro) { y ( ) } >> { z() }

x(); y(); z();

$(macro) { z ( ) } >> { a() }

x(); y(); z();

?>
--EXPECTF--
<?php

z(); z(); z();

a(); a(); a();

?>
