--TEST--
Test compiler pass arguments --pretty-print
--FILE--
<?php

$(macro) {
    $(T_VARIABLE as A)($(T_VARIABLE as B))
    $(_() as debug)
}
>> function(\Yay\Ast $ast, \Yay\TokenStream $ts, \Yay\Index $start, \Yay\Index $end, \Yay\Engine $engine){
    ob_start();
    var_dump($ast, $ts, $start, $end, get_class($engine));
    $result = PHP_EOL . ob_get_clean();

    $ast->append(new \Yay\Ast('debug', new \Yay\Token(T_CONSTANT_ENCAPSED_STRING, $result)));
}
>> {
    $$(stringify($(debug)))
}

$x($y);

?>
--EXPECTF--
<?php

'
object(Yay\\Ast)#%d (%d) {
  ["label":protected]=>
  string(0) ""
  ["ast":protected]=>
  array(%d) {
    ["A"]=>
    object(Yay\\Token)#%d (%d) {
      [0]=>
      string(14) "T_VARIABLE($x)"
    }
    [0]=>
    object(Yay\\Token)#%d (%d) {
      [0]=>
      string(%d) "\'(\'"
    }
    ["B"]=>
    object(Yay\\Token)#%d (%d) {
      [0]=>
      string(14) "T_VARIABLE($y)"
    }
    [1]=>
    object(Yay\\Token)#%d (%d) {
      [0]=>
      string(%d) "\')\'"
    }
    [2]=>
    array(%d) {
    }
  }
  ["meta":"Yay\\Ast":private]=>
  NULL
}
object(Yay\\TokenStream)#%d (%d) {
  ["first":protected]=>
  object(Yay\\NodeStart)#%d (%d) {
  }
  ["current":protected]=>
  object(Yay\\Node)#%d (%d) {
    [0]=>
    object(Yay\\Token)#%d (%d) {
      [0]=>
      string(%d) "\';\'"
    }
  }
  ["last":protected]=>
  object(Yay\\NodeEnd)#%d (%d) {
  }
}
object(Yay\\Node)#%d (%d) {
  [0]=>
  object(Yay\\Token)#%d (%d) {
    [0]=>
    string(14) "T_VARIABLE($x)"
  }
}
object(Yay\\Node)#%d (%d) {
  [0]=>
  object(Yay\\Token)#%d (%d) {
    [0]=>
    string(%d) "\')\'"
  }
}
string(10) "Yay\\Engine"
';

?>
