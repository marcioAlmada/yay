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
        private $expected;

        function __construct($type, Token $token)
        {
            $this->type = $type;
            $this->stack = [$token];
            $this->expected = new Expected($token);
        }

        final function parse(TokenStream $ts) /*: Result|null*/
        {
            if (null !== ($token = $ts->current()) && $token->equals($this->stack[0])) {
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

            if (null !== $token && 1 === preg_match($regexp, (string) $token)) {
                $ts->next();

                return new Ast($this->label, $token);
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(new Token(Token::MATCH, $this->stack[0]));
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

function always(Token $result) : Parser
{
    return new class(__FUNCTION__, $result) extends Parser
    {
        protected function parser(TokenStream $ts, Token $result) /*: Result|null*/
        {
            return new Ast($this->label, [($this->label ?: 0) => clone $result]);
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
                ($token = $ts->current()) &&
                (false !== mb_strstr($operator, ($current = (string) $token)))
            ){
                $ts->step();
                if(($buffer .= $current) === $operator) {
                    $ts->skip(...TokenStream::SKIPPABLE);
                    return new Ast($this->label, new Token(token::OPERATOR, $buffer));
                }
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(new Token(token::OPERATOR, $this->stack[0]));
        }

        function isFallible() : bool
        {
            return true;
        }
    };
}

function traverse(Parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) /*: Result|null*/
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
            'Infinite loop at ' . __FUNCTION__ . '('. $parser->type() . '(*))');

    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) /*: Result|null*/
        {
            $ast = new Ast($this->label);

            while(
                ($current = $ts->current()) &&
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
    return new class(__FUNCTION__, $a, commit($b), commit($c)) extends Parser
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

if(!defined(__NAMESPACE__."\\LAYER_DELIMITERS")) {
    define(__NAMESPACE__."\\LAYER_DELIMITERS", [
        '{' => 1,
        T_CURLY_OPEN => 1,
        T_DOLLAR_OPEN_CURLY_BRACES => 1,
        '}' => -1,
        '[' => 1,
        ']' => -1,
        '(' => 1,
        ')' => -1,
    ]);
}

function layer(array $delimiters = LAYER_DELIMITERS) : Parser
{
    return new class(__FUNCTION__, $delimiters) extends Parser
    {
        function parser(TokenStream $ts, array $delimiters) /*: Result|null*/
        {
            $level = 1;
            $tokens = [];

            while (
                ($token = $ts->current()) &&
                ($level += ($delimiters[$token->type()] ?? 0))
            ){
                $tokens[] = $token;
                $ts->step();
            }

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
            layer()
            ,
            token('}')
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
            layer()
            ,
            token(']')
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
            layer()
            ,
            token(')')
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

if(!defined(__NAMESPACE__."\\CONSUME_DO_TRIM")) define(__NAMESPACE__."\\CONSUME_DO_TRIM", 0x10);
if(!defined(__NAMESPACE__."\\CONSUME_NO_TRIM")) define(__NAMESPACE__."\\CONSUME_NO_TRIM", 0x01);

function consume(Parser $parser, int $trim = CONSUME_NO_TRIM) : Parser
{
    return new class(__FUNCTION__, $parser, $trim) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, int $trim) /*: Result|null*/
        {
            $from = $ts->index();
            $ast = $parser->parse($ts);
            if ($ast instanceof Ast) {
                $ts->unskip(...TokenStream::SKIPPABLE);
                if ($trim & CONSUME_DO_TRIM) $ts->skip(T_WHITESPACE);
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

function optional(Parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser) : Ast
        {
            $result = $parser->parse($ts);
            $match = ($result instanceof Ast) ? $result->raw() : [];

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
            $result = $parser->parse($ts);

            if ($result instanceof Ast) return $result->as($this->label);

            if ($result instanceof Error) $result->halt();

            $parser->withErrorLevel(Error::ENABLED)->error($ts)->halt();
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
                either
                (
                    token(T_NS_SEPARATOR)
                    ,
                    token(T_NAMESPACE)
                    ,
                    chain
                    (
                        token(T_NS_SEPARATOR)
                        ,
                        token(T_NAMESPACE)
                    )
                )
            )
            ,
            repeat
            (
                either
                (
                    chain
                    (
                        token(T_NS_SEPARATOR)
                        ,
                        token(T_STRING)
                    )
                    ,
                    token(T_STRING)
                )
            )
        );
}

function ls(Parser $parser, Parser $delimiter) : Parser
{
    return new class(__FUNCTION__, $parser, $delimiter) extends Parser
    {
        protected function parser(TokenStream $ts, Parser $parser, Parser $delimiter) /*: Result|null*/
        {
            $ast = new Ast($this->label);
            $parser = (clone $parser)->onCommit(function(ast $result) use ($ast){
                $ast->push($result);
            });

            repeat
            (
                either
                (
                    chain
                    (
                        $delimiter
                        ,
                        $parser
                    )
                    ,
                    $parser
                )
            )
            ->parse($ts);

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

function future(&$parser) : Parser
{
    $delayed = function() use(&$parser) : Parser { return clone $parser; };

    return new class(__FUNCTION__, $delayed) extends Parser
    {
        protected function parser(TokenStream $ts, callable $delayed) /*: Result|null*/
        {
            $result = $delayed()->parse($ts);
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

function word() : Parser
{
    return rtoken('/^\w+$/');
}

function string() : Parser
{
    return token(T_CONSTANT_ENCAPSED_STRING);
}
