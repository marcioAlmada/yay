--TEST--
Uses a custom fully qualified expansion function --pretty-print
--FILE--
<?php

$(macro) {
    hello($(token(T_STRING) as matched));
} >> {
    $$(\Yay\tests\fixtures\expanders\my_cheers_tokenstream_expander(
    	$$(\Yay\tests\fixtures\expanders\my_hello_tokenstream_expander(
    		$(matched)
    	))
    ));
}

hello(Chris);

?>
--EXPECTF--
<?php

'Hello, Chris. From TokenStream. Cheers!';

?>
