<?php namespace Yay\tests\fixtures\parsers;

use Yay\{Parser, Ast, Token};
use function Yay\{token, midrule};

function my_custom_parser() : Parser {
	return token(T_STRING);
}

function var_dump_args_parser() : Parser {
	ob_start();
	var_dump(...func_get_args());
	$dumped = ob_get_clean();
	return midrule(function($_, $label) use($dumped) : Ast {
		return new Ast('var_dump', new Token(T_CONSTANT_ENCAPSED_STRING, "/*\n{$label}: {$dumped}*/"));
	});
}
