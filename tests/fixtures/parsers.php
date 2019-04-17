<?php namespace Yay\tests\fixtures\parsers;

use Yay\{Parser};
use function Yay\{token, chain, buffer};

function my_custom_parser() : Parser {
	return token(T_STRING);
}
