<?php namespace Yay\tests\fixtures\parsers;

use Yay\{Parser};
use function Yay\{token};

function my_custom_parser() : Parser {
	return token(T_STRING);
}

function my_custom_parser_with_default_alias() : Parser {
	return token(T_STRING)->as('default_alias_from_custom_parser');
}
