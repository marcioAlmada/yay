<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

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
            parent::{__FUNCTION__}($type, $token);
            $this->type = $type;
            $this->token = $token;
            $this->stack = $token;
            $this->expected = new Expected($token);
        }

        final function parse(TokenStream $ts) /*: Result|null*/
        {
            try {
                self::$tracer->push($this);

                self::$tracer->trace($ts->index(), 'attempt');

                if (null !== ($token = $ts->current()) && $token->equals($this->token)) {
                    self::$tracer->trace($ts->index(), 'production', (string) $token);

                    $ts->next();
                    $result = new Ast($this->label, $token);
                    if (null !== $this->onCommit) ($this->onCommit)($result);

                    return $result;
                }

                self::$tracer->trace($ts->index(), 'error');

                if ($this->errorLevel === Error::ENABLED)
                    return new Error($this->expected, $token, $ts->last());
            }
            finally {
                self::$tracer->pop($this);
            }
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

function buffer(string $match) : Parser
{
    return new class(__FUNCTION__, trim($match)) extends Parser
    {
        protected function parser(TokenStream $ts, string $match) /*: Result|null*/
        {
            $max = mb_strlen($match);
            $buffer = '';
            $tokens = [];

            while (
                (mb_strlen($buffer) <= $max) &&
                (null !== ($token = $ts->current())) &&
                (false !== mb_strstr($match, ($current = $token->value())))
            ){
                $ts->step();
                $tokens[] = $token;
                if(($buffer .= $current) === $match) {
                    $ts->skip();
                    return new Ast($this->label, $tokens);
                }
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(new Token(Token::BUFFER, $this->stack[0]));
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
 *     repeat
 *     (
 *         either
 *         (
 *             <parser 1>,
 *             <parser 2>,
 *             <parser 3,
 *             <parser ...>,
 *             any()
 *         )
 *     )
 * ;
 *
 * while($parser->parse($subject) instanceof Ast);
 * </code>
 */
function traverse(Parser ...$parsers) : Parser
{
    return new class(__FUNCTION__, either(...$parsers), any()) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, Parser $any) : Ast
        {
            while ($parser->parse($ts) instanceof Ast || $any->parse($ts) instanceof Ast);

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
    return new class(__FUNCTION__, repeat($parser)) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) /*: Result|null*/
        {
            $set = new Ast;
            $ast = $parser->parse($ts);
            if ($ast instanceof Ast)
                foreach ($ast->list() as $branch)
                    foreach ($branch->list() as $leaf) $set->append($leaf);

            return $set->isEmpty() ? $this->error($ts) : $set;
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
            $ast = new Ast($this->label);

            foreach ($links as $i => $link) {
                if (($result = $link->parse($ts)) instanceof Ast) {
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

        /**
         * Optimizes either() parser stack from O(n²) to O(n) lookup table,
         * specially effective when grammars with direct recursion are difficult to avoid.
         *
         * The parser produces the exact same results with or without the optimization, except for
         * noticeable performance improvements on very long either() try lists.
         */
        function optimize() : Parser
        {
            parent::optimize();

            $jumptable = [];
            $parsers = $this->stack;

            foreach ($parsers as $parser)
                if (count($parser->expected()->all()))
                    foreach ($parser->expected()->all() as $prefixToken)
                        $jumptable[$prefixToken->type()][] = $parser->optimize();
                else
                    throw new \Exception("Cannot optimize {$this} parser stack at {$parser}");

            foreach ($jumptable as $prefix => $possibleRoutes) {
                if (count($possibleRoutes) > 1) $jumptable[$prefix] = either(...$possibleRoutes);
                else $jumptable[$prefix] = $possibleRoutes[0];
            }

            return (new class ('*' . $this->type, $jumptable, $this) extends Parser {

                function parser($ts, array $jumptable, Parser $wrapped) {
                    if (null !== ($token = $ts->current()) && ($parser = $jumptable[$token->type()] ?? null)) {
                        if (($result = $parser->parse($ts)) instanceof Ast)
                            if ($this->label && $result->label())
                                $result = (new Ast($this->label))->append($result);
                            else
                                $result->as($this->label);

                        return $result;
                    }

                    return $wrapped->error($ts);
                }

                function expected() : Expected
                {
                    return $this->stack[1]->expected();
                }

                function isFallible() : bool
                {
                    return $this->stack[1]->isFallible();
                }

                /**
                 * Disables further optimizations on already optimized parsers, preventing infinite
                 * recursion during the optimization cycle
                 */
                function optimize() : Parser
                {
                    return $this;
                }

            })
            ->as($this->label);
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

const
    LS_KEEP_DELIMITER = 0x1,
    LS_DISCARD_DELIMITER = 0x0
;

function ls(Parser $parser, Parser $delimiter, int $flags = LS_DISCARD_DELIMITER) : Parser
{
    if (! $parser->isFallible())
        throw new InvalidArgumentException(
            'Infinite loop at ' . __FUNCTION__ . '('. $parser . '(*))');

    return new class(__FUNCTION__, $parser, $delimiter, $flags) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, Parser $delimiterParser, int $flags) /*: Result|null*/
        {
            $ast = new Ast($this->label);
            $stack = [];
            if (($item = $parser->parse($ts)) instanceof Ast) {
                $stack[] = [$item, null];
                while (
                    ($index = $ts->index()) &&
                    ($delimiter = $delimiterParser->parse($ts)) instanceof Ast &&
                    ($item = $parser->parse($ts)) instanceof Ast
                ) {
                    $stack[count($stack)-1][1] = $delimiter;
                    $stack[] = [$item, null];
                }

                if (! ($item instanceof Ast)) $ts->jump($index);

                while($tuple = array_shift($stack)) {
                    if (($flags & LS_DISCARD_DELIMITER) === $flags) {
                        $ast->push($tuple[0]);
                    }
                    else {
                        $ast->push(new Ast('', ['item' => $tuple[0], 'delimiter' => $tuple[1]]));
                    }
                }

            }

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

function lst(Parser $parser, Parser $delimiter, int $flags = LS_DISCARD_DELIMITER) : Parser
{
    if (! $parser->isFallible())
        throw new InvalidArgumentException(
            'Infinite loop at ' . __FUNCTION__ . '('. $parser . '(*))');

    return new class(__FUNCTION__, $parser, $delimiter, $flags) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, Parser $delimiterParser, int $flags) /*: Result|null*/
        {
            $ast = new Ast($this->label);
            $stack = [];
            if (($item = $parser->parse($ts)) instanceof Ast) {
                $stack[] = [$item, null];
                while (
                    ($index = $ts->index()) &&
                    ($delimiter = $delimiterParser->parse($ts)) instanceof Ast &&
                    ($item = $parser->parse($ts)) instanceof Ast
                ) {
                    $stack[count($stack)-1][1] = $delimiter;
                    $stack[] = [$item, null];
                }

                if (! ($item instanceof Ast)) $stack[count($stack)-1][1] = $delimiter;

                while($tuple = array_shift($stack)) {
                    if (($flags & LS_DISCARD_DELIMITER) === $flags) {
                        $ast->push($tuple[0]);
                    }
                    else {
                        $ast->push(new Ast('', ['item' => $tuple[0], 'delimiter' => $tuple[1]]));
                    }
                }

            }

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

function pointer(&$parser) : Parser
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
            $this->preventCircularPointerDereference();

            return $this->stack[0]()->expected();
        }

        function isFallible() : bool
        {
            return true;
        }

        function optimize() : Parser
        {
            $this->preventCircularPointerDereference();

            $parser = $this->stack[0]();
            $parser->type = '*' . $parser->type;

            if ($this->errorLevel === Error::ENABLED)
                $parser->withErrorLevel($this->errorLevel);

            return $parser->optimize();
        }

        private function preventCircularPointerDereference()
        {
            if (pointer::class === $this->type && $this->stack[0] === $this->stack[0]()->stack[0])
                throw new \Exception("Circular pointer dereference at {$this}.");
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
            return ($result instanceof Ast) ? $this->error($ts) : new Ast;
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

function midrule(callable $midrule, bool $isFallible = true, Expected $expected = null) : Parser
{
    return new  class(__FUNCTION__, $midrule, $expected ?: new Expected, $isFallible) extends Parser
    {
        function parser(TokenStream $ts) /*: Result|null*/
        {
            $result = $this->stack[0]($ts);

            if ($result instanceof Ast) $result->as($this->label);

            return $result;
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

function _() : Parser {
    return new  class(__FUNCTION__) extends Parser
    {
        function parser() : Ast
        {
            return new Ast;
        }

        function expected() : Expected
        {
            return new Expected;
        }

        function isFallible() : bool
        {
            return false;
        }

        function as(string $_) : Parser
        {
            return $this;
        }
    };
}

function expression(string $namespace = '') : Parser
{
    static $repository = [];

    $namespace = md5(__NAMESPACE__);

    return $repository[$namespace] ?? $repository[$namespace] = new ExpressionParser;
}
