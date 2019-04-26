--TEST--
Test for constant array as parsec arguments
--FILE--
<?php

$(macro) {

    dummy

    $(chain(
        \Yay\tests\fixtures\parsers\var_dump_args_parser([]) as empty_array
        ,
        \Yay\tests\fixtures\parsers\var_dump_args_parser([1, '2', 'foo', 'bar', 5, []]) as non_assoc_array,
        \Yay\tests\fixtures\parsers\var_dump_args_parser([
            1, '2', 'foo', 'bar', 5, [1, '2', 'foo', 'bar', 5, []]
        ]) as nested_non_assoc_array,
        \Yay\tests\fixtures\parsers\var_dump_args_parser([
            1 => 2, 3 => '4', '5' => 6, 'foo' => 'bar', 'baz' => [
                1 => 2, 3 => '4', '5' => 6, 'foo' => 'bar', 'baz' => 7
            ]
        ]) as nested_assoc_array,
        \Yay\tests\fixtures\parsers\var_dump_args_parser([[], ['foo' => []], 2 => ['bar' => [1, 2, 3]]]) as random_typing
    ) as var_dump)

    ;

} >> {
    $(var_dump)
}

dummy;

?>
--EXPECTF--
<?php

/*
empty_array: array(0) {
}
*//*
non_assoc_array: array(6) {
  [0]=>
  int(1)
  [1]=>
  string(1) "2"
  [2]=>
  string(3) "foo"
  [3]=>
  string(3) "bar"
  [4]=>
  int(5)
  [5]=>
  array(0) {
  }
}
*//*
nested_non_assoc_array: array(6) {
  [0]=>
  int(1)
  [1]=>
  string(1) "2"
  [2]=>
  string(3) "foo"
  [3]=>
  string(3) "bar"
  [4]=>
  int(5)
  [5]=>
  array(6) {
    [0]=>
    int(1)
    [1]=>
    string(1) "2"
    [2]=>
    string(3) "foo"
    [3]=>
    string(3) "bar"
    [4]=>
    int(5)
    [5]=>
    array(0) {
    }
  }
}
*//*
nested_assoc_array: array(5) {
  [1]=>
  int(2)
  [3]=>
  string(1) "4"
  [5]=>
  int(6)
  ["foo"]=>
  string(3) "bar"
  ["baz"]=>
  array(5) {
    [1]=>
    int(2)
    [3]=>
    string(1) "4"
    [5]=>
    int(6)
    ["foo"]=>
    string(3) "bar"
    ["baz"]=>
    int(7)
  }
}
*//*
random_typing: array(3) {
  [0]=>
  array(0) {
  }
  [1]=>
  array(1) {
    ["foo"]=>
    array(0) {
    }
  }
  [2]=>
  array(1) {
    ["bar"]=>
    array(3) {
      [0]=>
      int(1)
      [1]=>
      int(2)
      [2]=>
      int(3)
    }
  }
}
*/

?>
