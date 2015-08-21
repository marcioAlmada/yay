<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

function token($type, $value = null) : Parser
{
    $token = $type instanceof token ? $type : new Token($type, $value);

    return new class(__FUNCTION__, $token) extends Parser
    {
        function parser(TokenStream $ts, token $expected) : Result
        {
            if (($token = $ts->current()) && $token->equals($expected)) {
                $ts->next();

                return new Ast($this->label, $token);
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected($this->stack[0]);
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
        protected function parser(TokenStream $ts, string $regexp) : Result
        {
            $token = $ts->current();

            if ($token && 1 === preg_match($regexp, (string) $token)) {
                $ts->next();

                return new Ast($this->label, $token);
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(Token::match($this->stack[0]));
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
        protected function parser(TokenStream $ts) : Result
        {
            if ($token = $ts->current()) {
                $ts->next();

                return new Ast($this->label, $token);
            }

            return $this->error($ts);
        }

        function expected() : Expected
        {
            return new Expected(Token::any());
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
        protected function parser(TokenStream $ts) : Result
        {
            if (($token = $ts->step(-1)) && $token->is(T_WHITESPACE)) {
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

function always($result) : Parser
{
    return new class(__FUNCTION__, $result) extends Parser
    {
        protected function parser(TokenStream $ts, $result) : Result
        {
            return new Ast($this->label, [$this->label ?: 0 => $result]);
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
        protected function parser(TokenStream $ts, string $operator) : Result
        {
            $max = mb_strlen($operator);
            $buffer = '';

            while ((mb_strlen($buffer) <= $max) && $current = $ts->current()) {
                $buffer .= (string) $current;
                $ts->step();
                if($buffer === $operator) {
                    $ts->skip(T_WHITESPACE);

                    return new Ast($this->label, Token::operator($buffer));
                }
            }

            return $this->error($ts, "'{$buffer}'");
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

function repeat(parser $parser, parser $until = null) : Parser
{
    if (! $parser->isFallible())
        throw new InvalidArgumentException(
            'Infinite loop at ' . __FUNCTION__ . '('. $parser->type() . '(*))');

    return new class(__FUNCTION__, $parser, $until->stack[0] ?? null) extends Parser
    {
        protected function parser(TokenStream $ts, parser $parser, token $until = null) : Result
        {
            $ast = new Ast($this->label);
            $index = $ts->index();

            if ($until) $parser = commit($parser);

            while(true) {
                if (
                    ! ($current = $ts->current()) ||
                    ($until && $current->equals($until)) ||
                    ($result = $parser->parse($ts)) instanceof error
                ){
                    $result = $result ?? $this->error($ts);
                    $ts->jump($index);
                    if ($ast->isEmpty()) $ast = $result;
                    break;
                }

                $index = $ts->index();
                $ast->append($result);
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

function between(parser $a, parser $b, parser $c): Parser
{
    return new class(__FUNCTION__, $a, commit($b), commit($c)) extends Parser
    {
        protected function parser(TokenStream $ts, parser $a, parser $b, parser $c) : Result
        {
            $asts = [];

            foreach ([$a, $b, $c] as $parser) {
                $result = $parser->parse($ts);
                if ($result instanceof ast)
                    $asts[] = $result;
                else
                    return $result;
            }

            return (new Ast($b->label ?: $this->label))->merge($asts[1]);
        }

        function expected() : Expected
        {
            $tokens = new Expected();

            foreach ($this->stack as $substack) {
                $tokens->append($substack->expected());
                if (optional::class !== $substack->type()) break;
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

function layer() : Parser
{
    return new class(__FUNCTION__) extends Parser
    {
        protected $delimiters = [
            '{' => 1,
            T_CURLY_OPEN => 1,
            T_DOLLAR_OPEN_CURLY_BRACES => 1,
            '}' => -1,
            '[' => 1,
            ']' => -1,
            '(' => 1,
            ')' => -1,
        ];

        function parser(TokenStream $ts) : Result
        {
            $level = 1;
            $tokens = [];

            while (
                ($token = $ts->current()) &&
                ($level += ($this->delimiters[$token->type()] ?? 0))
            ){
                $tokens[] = $token;
                $ts->step();
            }

            return new Ast($this->label, $tokens);
        }

        function expected() : Expected {
            return new Expected([]);
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

function chain(parser ...$links) : Parser
{
    return new class(__FUNCTION__, ...$links) extends Parser
    {
        protected function parser(TokenStream $ts, parser ...$links) : Result
        {
            $asts = [];
            $ast = new Ast($this->label);

            foreach ($links as $i => $link) {
                if (($result = $link->parse($ts)) instanceof ast) {
                    $asts[$i] = $result;
                    $ast->append($result);
                }
                else {
                    while ($i-- && $links[$i]->is(optional::class) && ! $asts[$i]->isEmpty()) {
                       $result->push($links[$i]->error($ts));
                    }

                    return $result;
                }
            }

            return $ast;
        }

        function expected() : Expected
        {
            $tokens = new Expected();

            foreach ($this->stack as $substack) {
                $tokens->append($substack->expected());
                if (optional::class !== $substack->type()) break;
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

function either(parser ...$routes) : Parser
{
    $last = count($routes) - 1;
    foreach ($routes as $i => $route)
        if (! $route->isFallible() && $i !== $last && ++$i) {
            $u = $routes[$i];
            throw new InvalidArgumentException(
                "Unreachable {$u->type()}() parser at "
                . __FUNCTION__ . "(...)[{$i}]"
            );
        }

    return new class(__FUNCTION__, ...$routes) extends Parser
    {
        protected function parser(TokenStream $ts, parser ...$routes) : Result
        {
            $errors = [];

            foreach ($routes as $route)
                if(($result = $route->parse($ts)) instanceof ast)
                    return $result;
                else
                    $errors[] = $result;

            $error = array_shift($errors);
            foreach ($errors as $f) $error->push($f);

            return $error;
        }

        function expected() : Expected
        {
            $tokens = new Expected();

            foreach ($this->stack as $substack) {
                $tokens->append($substack->expected());
                if ($substack->is(optional::class)) break;
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
    SWALLOW_DO_TRIM = 0x10,
    SWALLOW_NO_TRIM = 0x01
;

function swallow(parser $parser, int $trim = SWALLOW_NO_TRIM) : Parser
{
    return new class(__FUNCTION__, $parser, $trim) extends Parser
    {
        protected function parser(TokenStream $ts, parser $parser, int $trim) : Result
        {
            $from = $ts->index();
            $ast = $parser->parse($ts);
            if ($ast instanceof ast) {
                $ts->unskip(TokenStream::SKIPPABLE);
                if ($trim & SWALLOW_DO_TRIM) $ts->skip(T_WHITESPACE);
                $to = $ts->index();
                if ($from < $to) $ts->extract($from, $to);

                return (new Ast($parser->label ?: $this->label))->merge($ast);
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

function lookahead(parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, parser $parser) : Result
        {
            $label = $parser->label ?: $this->label;
            $index = $ts->index();
            $result = $parser->parse($ts);
            $ts->jump($index);
            if ($result instanceof ast) return (new Ast($label))->merge($result);

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

function optional(parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, parser $parser) : Ast
        {
            $result = $parser->parse($ts);
            $ast = new Ast($parser->label ?: $this->label);

            if ($result instanceof ast) $ast->merge($result);

            return $ast;
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

function commit(parser $parser) : Parser
{
    return new class(__FUNCTION__, $parser) extends Parser
    {
        protected function parser(TokenStream $ts, parser $parser) : Ast
        {
            $result = $parser->parse($ts);

            if ($result instanceof error) $result->halt();

            return (new Ast($parser->label ?: $this->label))->merge($result);
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

function ls(parser $parser, parser $delimiter) : Parser
{
    return new class(__FUNCTION__, $parser, $delimiter) extends Parser
    {
        protected function parser(TokenStream $ts, parser $parser, parser $delimiter) : Result
        {
            $ast = new Ast($this->label);

            repeat
            (
                either
                (
                    chain
                    (
                        $delimiter
                        ,
                        (clone $parser)
                    )
                    ->onCommit(function(ast $result) use ($ast, $parser){
                        $ast->push(new Ast($parser->label, $result->{$parser->label ?: 1}));
                    })
                    ,
                    (clone $parser)->onCommit(function(ast $result) use ($ast){
                        $ast->push($result);
                    })
                )
            )
            ->parse($ts);

            return $ast;
        }

        function expected() : Expected
        {
            return $this->stack[0]()->expected();
        }

        function isFallible() : bool
        {
            return $this->stack[0]()->isFallible();
        }
    };
}

function future(&$parser) : Parser
{
    $delayed = function() use(&$parser) : parser { return $parser; };

    return new class(__FUNCTION__, $delayed) extends Parser
    {
        protected function parser(TokenStream $ts, $delayed) : Result
        {
            return $delayed()->parse($ts);
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
