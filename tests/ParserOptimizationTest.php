<?php declare(strict_types=1);

namespace Yay;

/**
 * Repeats the same tests in ParserTest but calling Parser::optimize() first
 * 
 * @group small
 */
class ParserOptimizationTest extends ParserTest {
    protected function parseSuccess(TokenStream $ts, Parser $parser, string $expected = null) : Ast {
        return parent::parseSuccess($ts, $parser->optimize(), $expected);
    }
}
