--TEST--
Ensures preprocessor syntax errors occurs in the right line number
--FILE--
<?php

$(macro) {

	$(token(T_STRING, 'foo') as captured);

} >> {

	function expansion()
	{
		$(captured) $(captured);
	}

}

$(macro) {

	$(token(T_STRING, 'foo')) $! expected

} >> {

	END;
}

foo; // L:25

?>
--EXPECTF--
Unexpected T_STRING(foo) on line 25, expected T_STRING(expected).
