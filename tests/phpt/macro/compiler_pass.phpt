--TEST--
Test compiler pass arguments --pretty-print
--FILE--
<?php

macro {
    T_VARIABLE·A(T_VARIABLE·B)
    ·_()·debug
}
>> function(\Yay\Ast $ast, \Yay\TokenStream $ts, \Yay\Node $start, \Yay\Node $end){
    ob_start();
    var_dump($ast, $ts, $start, $end);
    $result = PHP_EOL . ob_get_clean();

    $ast->append(new \Yay\Ast('·debug', new \Yay\Token(T_CONSTANT_ENCAPSED_STRING, $result)));
}
>> {
    ··stringify(·debug)
}

$x($y);

?>
--EXPECTF--
<?php

'
object(Yay\\Ast)#%s (3) {
  ["label":protected]=>
  NULL
  ["ast":protected]=>
  array(5) {
    ["T_VARIABLE·A"]=>
    object(Yay\\Token)#%s (1) {
      [0]=>
      string(14) "T_VARIABLE($x)"
    }
    [0]=>
    object(Yay\\Token)#%s (1) {
      [0]=>
      string(3) "\'(\'"
    }
    ["T_VARIABLE·B"]=>
    object(Yay\\Token)#%s (1) {
      [0]=>
      string(14) "T_VARIABLE($y)"
    }
    [1]=>
    object(Yay\\Token)#%s (1) {
      [0]=>
      string(3) "\')\'"
    }
    [2]=>
    array(0) {
    }
  }
  ["parent":"Yay\\Ast":private]=>
  NULL
}
object(Yay\\TokenStream)#%s (3) {
  ["first":protected]=>
  object(Yay\\NodeStart)#%s (0) {
  }
  ["current":protected]=>
  object(Yay\\Node)#%s (1) {
    [0]=>
    object(Yay\\Token)#%s (1) {
      [0]=>
      string(3) "\';\'"
    }
  }
  ["last":protected]=>
  object(Yay\\NodeEnd)#%s (0) {
  }
}
object(Yay\\Node)#%s (1) {
  [0]=>
  object(Yay\\Token)#%s (1) {
    [0]=>
    string(14) "T_VARIABLE($x)"
  }
}
object(Yay\\Node)#%s (1) {
  [0]=>
  object(Yay\\Token)#%s (1) {
    [0]=>
    string(3) "\')\'"
  }
}
';

?>

