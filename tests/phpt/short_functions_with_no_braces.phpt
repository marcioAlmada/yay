--TEST--
legit arrow functions with lexical scoping

use --pretty-print

--FILE--
<?php

macro ·recursion {
    ( // literraly match '('

    // the arglist with type hinting support:
    ·ls(·chain(·optional(·ns()·type), ·token(T_VARIABLE)·arg_name)·arg,·token(','))·args

    ) // literraly match ')'

    ·optional(·chain(·token(':'),·ns()))·return_type // the optional return type

    ·buffer('==>')·_ // the swimming arrow operator

    ·expression()·single_expression_body // the short closure's body

    ·_()·scope // dummy label signaling that ·scope exists, it's added dynamically through the compiler pass

} >> function($ast) {
    $defined = [];
    foreach ($ast->{'·args'} as $node) $defined[(string) $node['·arg']['·arg_name']] = true;

    $scoped = [];
    $scope = new \Yay\Ast('·scope');
    foreach ($ast->{'* ·single_expression_body'}->tokens() as $token) {
        if (
            $token->is(T_VARIABLE) &&
            ('$this' !== (string) $token) &&
            false === isset($defined[(string) $token]) &&
            false === isset($scoped[(string) $token])
        ){
            $scope->push(new \Yay\Ast('·var', $token));
            $scoped[(string) $token] = true;
        }
    }

    $ast->append($scope);
} >> {
  ·scope ?·{
      [
          ·scope ···(, ) { ·var = ·var ?? null},
          'short_closure' => function (·args ···(, ){ ·arg ···{·type ·arg_name}}) use(·scope ···(, ) { ·var }) ·return_type {
                  return ·single_expression_body;
          }
      ]['short_closure']
  }
  ·scope !· {
    function (·args ···(, ){ ·arg ···{·type ·arg_name}}) ·return_type {
          return ·single_expression_body;
    }
  }
}

$y = 100;
//
$result = array_map((int $x):int ==> $x * 2 * ++$y , range(1, 10));
//
assert($y === 100);
var_dump($result);

?>
--EXPECTF--
<?php

$y = 100;
//
$result = array_map([$y = $y ?? null, 'short_closure' => function (int $x) use($y) : int {
    return $x * 2 * ++$y;
}]['short_closure'], range(1, 10));
//
assert($y === 100);
var_dump($result);

?>

