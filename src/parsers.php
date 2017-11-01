<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

function variable(): Parser
{
    return rtoken('/\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/');
}

function phpOperator(): Parser
{
    return either(
        token('='),                    // =        
        token('+'),                    // +        
        token('-'),                    // -        
        token('*'),                    // *        
        token('/'),                    // /        
        token('%'),                    // %        
        token('^'),                    // ^        
        token('|'),                    // |        
        token('&'),                    // &        
        token(T_POW),                  // **        
        token(T_SR),                   // >>        
        token(T_SL),                   // <<        
        token(T_PLUS_EQUAL),           // +=   
        token(T_MINUS_EQUAL),          // -=    
        token(T_MUL_EQUAL),            // *=
        token(T_POW_EQUAL),            // **=
        token(T_DIV_EQUAL),            // /=
        token(T_CONCAT_EQUAL),         // .=
        token(T_MOD_EQUAL),            // %=
        token(T_AND_EQUAL),            // &=
        token(T_OR_EQUAL),             // |=
        token(T_XOR_EQUAL),            // ^=
        token(T_SL_EQUAL),             // <<=
        token(T_SR_EQUAL),             // >>=
        token(T_IS_EQUAL),             // ==
        token(T_IS_IDENTICAL),         // ===
        token(T_IS_GREATER_OR_EQUAL),  // >=
        token(T_IS_NOT_EQUAL),         // != or <>
        token(T_IS_NOT_IDENTICAL),     // !==
        token(T_IS_SMALLER_OR_EQUAL),  // <=
        token(T_IS_EQUAL),             // ==
        token(T_SPACESHIP)             // <=>
    );
}

function expr(): Parser
{
    $expr = new class(__FUNCTION__) extends Parser
    {
        function parser(TokenStream $ts, ...$stack)
        {
            return expr()->parse($ts);
        }

        function expected() : Expected
        {
            return new Expected();
        }

        function isFallible() : bool
        {
            return true;
        }
    };

    return either(
        token(T_LNUMBER)->as('expr'),
        token(T_DNUMBER)->as('expr'),
        chain(
            chain(token(T_LIST), token('('), layer()->as('array_pair_list'), token(')'))->as('left'),
            operator('=')->as('operator'),
            $expr->as('right')
        )->as('expr'),
        chain(token('['), layer()->as('array_pair_list'), token(']'))->as('expr'),
        chain(
            variable()->as('left'),
            phpOperator()->as('operator'),
            $expr->as('right')
        )->as('expr'),
        chain(
            variable()->as('left'),
            operator('=')->as('operator'),
            chain(token('&'), variable())->as('right')
        )->as('expr'),
        chain(token(T_CLONE), $expr)->as('expr'),
        chain(
            chain(variable(), indentation())->as('left'),
            token(T_INSTANCEOF)->as('operator'),
            chain(indentation(), ns())->as('right')
        )->as('expr'),
        chain(token('('), $expr, token(')'))->as('expr'),
        variable()->as('expr')
    );
}

function token($type, $value = null) : Parser
{
    $token = $type instanceof Token ? $type : new Token($type, $value);

    return new class(__FUNCTION__, $token) extends Parser
    {
        private
            $expected,
            $token
        ;

        function __construct($type, Token $token)
        {
            $this->type = $type;
            $this->token = $token;
            $this->stack = $token;
            $this->expected = new Expected($token);
        }

        final function parse(TokenStream $ts) /*: Result|null*/
        {
            if (null !== ($token = $ts->current()) && $token->equals($this->token)) {
                $ts->next();
                $result = new Ast($this->label, $token);
                if (null !== $this->onCommit) ($this->onCommit)($result);

                return $result;
            }

            if ($this->errorLevel === Error::ENABLED)
                return new Error($this->expected, $token, $ts->last());
        }

        function expected() : Expected
        {
            return $this->expected;
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

function rtoken(string $regexp) : Parser
{
    if (false === preg_match($regexp, ''))
        throw new InvalidArgumentException('Invalid regexp at ' . __FUNCTION__);

    return new class(__FUNCTION__, $regexp) extends Parser
    {
        protected function parser(TokenStream $ts, string $regexp) /*: Result|null*/
        {
            $token = $ts->current();

            if (null !== $token && 1 === preg_match($regexp, $token->value())) {
                $ts->next();

                return new Ast($this->label, $token);
            }

            if ($this->errorLevel === Error::ENABLED)
                return new Error(new Expected(new Token(T_STRING, "matching '{$regexp}'")), $ts->current(), $ts->last());
        }

        function expected() : Expected
        {
            return new Expected(new Token(T_STRING), new Token(T_VARIABLE));
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

function any() : Parser
{
    return new class(__FUNCTION__) extends Parser
    {
        protected function parser(TokenStream $ts) /*: Result|null*/
        {
            if (null !== ($token = $ts->current())) {
                $ts->next();

                return new Ast($this->label, $token);
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(new Token(Token::ANY));
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

function indentation()
{
    return new class(__FUNCTION__) extends Parser
    {
        protected function parser(TokenStream $ts) /*: Result|null*/
        {
            if (null !== ($token = $ts->back()) && $token->is(T_WHITESPACE)) {
                $ts->step();

                return new Ast($this->label, $token);
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(new Token(T_WHITESPACE));
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

function always($type, $value = null) : Parser
{
    $token = $type instanceof Token ? $type : new Token($type, $value);

    return new class(__FUNCTION__, $token) extends Parser
    {
        protected function parser(TokenStream $ts, Token $token) /*: Result|null*/
        {
            return new Ast($this->label, [($this->label ?: 0) => $token]);
        }

        function expected() : Expected
        {
            return new Expected;
        }

        function isFallible() : bool
        {
            return false;
        }
    };
}

function operator(string $operator) : Parser
{
    return new class(__FUNCTION__, trim($operator)) extends Parser
    {
        protected function parser(TokenStream $ts, string $operator) /*: Result|null*/
        {
            $max = mb_strlen($operator);
            $buffer = '';

            while (
                (mb_strlen($buffer) <= $max) &&
                (null !== ($token = $ts->current())) &&
                (false !== mb_strstr($operator, ($current = $token->value())))
            ){
                $ts->step();
                if(($buffer .= $current) === $operator) {
                    $ts->skip();
                    return new Ast($this->label, new Token(Token::OPERATOR, $buffer));
                }
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(new Token(Token::OPERATOR, $this->stack[0]));
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

/**
 * Useful to perform oportunistic matches and transformations, this parser
 * attemps a list of parsers while steps towards the end of a token stream.
 *
 * <code>
 * traverse(<parser 1>, <parser 2>, <parser 3>, <...>)
 * </code>
 *
 * It's basically a shortcut for:
 *
 * <code>
 * $parser =
 *     either
 *     (
 *         <parser 1>,
 *         <parser 2>,
 *         <parser 3>,
 *         <...>,
 *         any()
 *     )
 * ;
 *
 * while($parser->parse($subject) instanceof Ast);
 * </code>
 */
function traverse(Parser ...$parsers) : Parser
{
    $parsers[] = any();
    $parser = either(...$parsers);

    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) : Ast
        {
            while ($parser->parse($ts) instanceof Ast);

            return new Ast($this->label);
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function repeat(Parser $parser) : Parser
{
    if (! $parser->isFallible())
        throw new InvalidArgumentException(
            'Infinite loop at ' . __FUNCTION__ . '('. $parser . '(*))');

    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) /*: Result|null*/
        {
            $ast = new Ast($this->label);

            while(
                (null !== $ts->current()) &&
                (($partial = $parser->parse($ts)) instanceof Ast)
            ){
                $ast->push($partial);
            }

            return $ast->isEmpty() ? ($partial ?? $this->error($ts)) : $ast;
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function set(Parser $parser) : Parser
{
    if (! $parser->isFallible())
        throw new InvalidArgumentException(
            'Infinite loop at ' . __FUNCTION__ . '('. $parser . '(*))');

    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) /*: Result|null*/
        {
            $ast = new Ast($this->label);

            while(
                (null !== $ts->current()) &&
                (($partial = $parser->parse($ts)) instanceof Ast)
            ){
                $ast->append($partial);
            }

            return $ast->isEmpty() ? ($partial ?? $this->error($ts)) : $ast;
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function between(Parser $a, Parser $b, Parser $c): Parser
{
    return new class(__FUNCTION__, $a, $b, $c) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $a, Parser $b, Parser $c) /*: Result|null*/
        {
            $asts = [];

            foreach ([$a, $b, $c] as $parser) {
                $result = $parser->parse($ts);
                if ($result instanceof Ast)
                    $asts[] = $result;
                else
                    return $result;
            }

            return $asts[1]->as($this->label);
        }

        function expected() : Expected
        {
            $tokens = new Expected;

            foreach ($this->stack as $substack) {
                $tokens->append($substack->expected());
                if ($substack->isFallible()) break;
            }

            return $tokens;
        }

        function isFallible() : bool
        {
            return
                $this->stack[0]->isFallible() ||
                $this->stack[1]->isFallible() ||
                $this->stack[2]->isFallible()
            ;
        }
    };
}

const LAYER_DELIMITERS = [
    '{' => 1,
    T_CURLY_OPEN => 1,
    T_DOLLAR_OPEN_CURLY_BRACES => 1,
    '}' => -1,
    '[' => 2,
    ']' => -2,
    '(' => 3,
    ')' => -3,
];

function layer(array $delimiters = LAYER_DELIMITERS) : Parser
{
    return new class(__FUNCTION__, $delimiters) extends Parser
    {
        function parser(TokenStream $ts, array $delimiters) /*: Result|null*/
        {
            $level = 1;
            $stack = [];
            $tokens = [];

            $current = $ts->index();
            while (true) {
                if ((null === ($token = $current->token))) break;

                $delimiter = $token->type();
                $factor = $delimiters[$delimiter] ?? 0;

                if ($factor > 0) {
                    $level++;
                    $stack[] = $delimiter;
                }
                else if ($factor < 0) {
                    $level--;
                    if ($pair = array_pop($stack)) {
                        if (($factor + $delimiters[$pair])!== 0) {
                            $ts->jump($current);

                            // reverse enginner delimiters to get the expected closing pair
                            $expected = array_search(-$delimiters[$pair], $delimiters);

                            return $this->error($ts, new Expected(new Token($expected)));
                        }
                    }
                }

                if ($level > 0) {
                    $tokens[] = $token;
                    $current = $current->next;
                    continue;
                }
                break;
            }
            $ts->jump($current);

            return new Ast($this->label, $tokens);
        }

        function expected() : Expected {
            return new Expected;
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

function braces(): Parser
{
    return
        between
        (
            token('{')
            ,
            commit(layer())
            ,
            commit(token('}'))
        )
    ;
}

function brackets(): Parser
{
    return
        between
        (
            token('[')
            ,
            commit(layer())
            ,
            commit(token(']'))
        )
    ;
}
function parentheses(): Parser
{
    return
        between
        (
            token('(')
            ,
            commit(layer())
            ,
            commit(token(')'))
        )
    ;
}

function chain(Parser ...$links) : Parser
{
    return new class(__FUNCTION__, ...$links) extends Parser
    {
        protected function parser(TokenStream $ts, Parser ...$links) /*: Result|null*/
        {
            $asts = [];
            $ast = new Ast($this->label);

            foreach ($links as $i => $link) {
                if (($result = $link->parse($ts)) instanceof Ast) {
                    $asts[$i] = $result;
                    $ast->append($result);
                }
                else {
                    $error = $result;
                    if ($this->errorLevel === Error::ENABLED) {
                        while (--$i >= 0 && ! $links[$i]->isFallible()) {
                            $lastest = $error;
                            $error = $links[$i]->error($ts);
                            $error->with($lastest);
                        }
                    }

                    return $error;
                }
            }

            return $ast;
        }

        function expected() : Expected
        {
            $tokens = new Expected;

            foreach ($this->stack as $substack) {
                $tokens->append($substack->expected());
                if ($substack->isFallible()) break;
            }

            return $tokens;
        }

        function isFallible() : bool
        {
            foreach ($this->stack as $substack)
                if ($substack->isFallible()) return true;

            return false;
        }
    };
}

function either(Parser ...$routes) : Parser
{
    $last = end($routes);
    foreach ($routes as $i => $route)
        if ($route !== $last && ! $route->isFallible()) {
            $parser = $routes[++$i];
            throw new InvalidArgumentException(
                "Dead {$parser}() parser at " . __FUNCTION__ . "(...[{$i}])");
        }

    return new class(__FUNCTION__, ...$routes) extends Parser
    {
        protected function parser(TokenStream $ts, Parser ...$routes) /*: Result|null*/
        {
            $errors = [];
            foreach ($routes as $route) {
                if (($result = $route->parse($ts)) instanceof Ast) {
                    if ($this->label && $route->label) {
                        $ret = new Ast($this->label);
                        $ret->append($result);
                        return $ret;
                    }
                    else
                        return $result->as($this->label);
                }
                if ($this->errorLevel === Error::ENABLED) {
                    if ($errors) end($errors)->with($result);
                    $errors[] = $result;
                }
            }

            return reset($errors) ?: null;
        }

        function expected() : Expected
        {
            $tokens = new Expected;

            foreach ($this->stack as $substack) {
                $tokens->append($substack->expected());
                if (! $substack->isFallible()) break;
            }

            return $tokens;
        }

        function isFallible() : bool
        {
            foreach ($this->stack as $substack)
                if (! $substack->isFallible()) return false;

            return true;
        }
    };
}

const
    CONSUME_DO_TRIM = 0x10,
    CONSUME_NO_TRIM = 0x01
;

function consume(Parser $parser, int $trim = CONSUME_NO_TRIM) : Parser
{
    return new class(__FUNCTION__, $parser, $trim) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, int $trim) /*: Result|null*/
        {
            $from = $ts->index();
            $ast = $parser->parse($ts);
            if ($ast instanceof Ast) {
                $ts->unskip();
                if ($trim & CONSUME_DO_TRIM) {
                    while (null !== ($token = $ts->current()) && $token->is(T_WHITESPACE)) {
                        $ts->step();
                    }
                }
                $to = $ts->index();
                $ts->extract($from, $to);

                return $ast->as($this->label);
            }

            return $ast;
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function lookahead(Parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) /*: Result|null*/
        {
            $index = $ts->index();
            $result = $parser->parse($ts);
            $ts->jump($index);
            if ($result instanceof Ast) return $result->as($this->label);

            return $result;
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return  $this->stack[0]->isFallible();
        }
    };
}

function optional(Parser $parser, $default = []) : Parser
{
    if ($default instanceof Parser)
        throw new InvalidArgumentException("optional() default value must not be <Parser>");

    return new class(__FUNCTION__, $parser, $default) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, $default) : Ast
        {
            $result = $parser->parse($ts);
            $match = ($result instanceof Ast) ? $result->unwrap() : $default;

            return (new Ast($parser->label, $match))->as($this->label);
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return false;
        }
    };
}

function commit(Parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) : Ast
        {
            $result = $parser->withErrorLevel(Error::ENABLED)->parse($ts);

            if ($result instanceof Error) $result->halt();

            return $result->as($this->label);
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function ns() : Parser
{
    return
        chain
        (
            optional
            (
                token(T_NS_SEPARATOR)
            )
            ->as('full-qualified')
            ,
            optional
            (
                chain
                (
                    token(T_NAMESPACE)
                    ,
                    token(T_NS_SEPARATOR)
                )
            )
            ,
            token(T_STRING)
            ,
            optional
            (
                repeat
                (
                    chain
                    (
                        token(T_NS_SEPARATOR)
                        ,
                        token(T_STRING)
                    )
                )
            )
        )
    ;
}

function ls(Parser $parser, Parser $delimiter) : Parser
{
    if (! $parser->isFallible())
        throw new InvalidArgumentException(
            'Infinite loop at ' . __FUNCTION__ . '('. $parser . '(*))');

    if ((string) $parser === __FUNCTION__)
        throw new InvalidArgumentException(
            'List parser unit must be labeled at ' . __FUNCTION__ . '('. $parser . ', ...)');

    return new class(__FUNCTION__, $parser, $delimiter) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, Parser $delimiter) /*: Result|null*/
        {
            $ast = new Ast($this->label);
            $midrule = function(Ast $result) use ($ast) { $ast->push($result); };

            chain
            (
                (clone $parser)->onCommit($midrule)
                ,
                optional
                (
                    repeat
                    (
                        chain
                        (
                            (clone $delimiter)
                            ,
                            (clone $parser)
                                ->onCommit($delimiter->label ? function(){} : $midrule)
                        )
                        ->onCommit($delimiter->label ? $midrule : function(){})
                    )
                )
            )
            ->parse($ts);

            return $ast->isEmpty() ? $this->error($ts) : $ast;
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function lst(Parser $parser, Parser $delimiter) : Parser
{

    $list = ls($parser, $delimiter);

    return new class(__FUNCTION__, $list, $delimiter) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $list, Parser $delimiter) /*: Result|null*/
        {
            $result = $list->as($this->label)->parse($ts);

            if ($result instanceof Ast)
                optional($delimiter)->parse($ts); // matches a possible trailing delimiter

            return $result;
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function future(&$parser) : Parser
{
    $delayed = function() use(&$parser) : Parser { return clone $parser; };

    return new class(__FUNCTION__, $delayed) extends Parser
    {
        protected function parser(TokenStream $ts, callable $delayed) /*: Result|null*/
        {
            $parser = $delayed();

            if ($this->errorLevel === Error::ENABLED)
                $parser->withErrorLevel($this->errorLevel);

            $result = $parser->parse($ts);

            if ($result instanceof Ast) $result->as($this->label);

            return $result;
        }

        function expected() : Expected
        {
            return $this->stack[0]()->expected();
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

function identifier() : Parser
{
    return token(T_STRING);
}

function label() : Parser
{
    return rtoken('/^\w+$/');
}

function string() : Parser
{
    return token(T_CONSTANT_ENCAPSED_STRING);
}

function not(Parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) /*: Result|null*/
        {
            $index = $ts->index();
            $result = $parser->parse($ts);
            $ts->jump($index); // always backtrack
            return ($result instanceof Ast) ? $this->error($ts) : new Ast();
        }

        function expected() : Expected
        {
            return $this->stack[0]->expected()->negate();
        }

        function isFallible() : bool
        {
            return $this->stack[0]->isFallible();
        }
    };
}

function closure() : Parser
{
    return
        chain
        (
            token(T_FUNCTION)->as('declaration'),
            token('('),
            layer()->as('arg_list'),
            token(')'),
            optional
            (
                chain
                (
                    token(T_USE)
                    ,
                    token('(')
                    ,
                    ls
                    (
                        either
                        (
                            chain(token('&'), token(T_VARIABLE)),
                            token(T_VARIABLE)
                        ),
                        token(',')
                    )
                    ->as('var_list'),
                    token(')')
                )
                ->as('use_list_decl')
            ),
            optional
            (
                chain
                (
                    token(':')
                    ,
                    ns()->as('return_type')
                )
                ->as('return_type_decl')
            ),
            token('{'),
            layer()->as('body'),
            token('}')
        )
    ;
}

function midrule(callable $midrule, bool $isFallible = true) : Parser
{
    return new  class(__FUNCTION__, $midrule, new Expected, $isFallible) extends Parser
    {
        function parse(TokenStream $ts) /*: Result|null*/
        {
            return $this->stack[0]($ts);
        }

        function expected() : Expected
        {
            return $this->stack[1];
        }

        function isFallible() : bool
        {
            return $this->stack[2];
        }
    };
}
