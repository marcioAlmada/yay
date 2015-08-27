<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

/**
 * This might need an interface
 */
abstract class Parser {

    protected
        $type,
        $label,
        $stack,
        $onTry,
        $onCommit
    ;

    abstract function expected() : Expected;

    abstract function isFallible() : bool;

    function __construct(string $type, ...$stack)
    {
        $this->type = $type;
        $this->stack = $stack;
    }

    final function __debugInfo()
    {
        return [$this->type, $this->label, $this->stack];
    }

    final function __clone()
    {
        $this->onTry = null;
        $this->onCommit = null;
    }

    final function parse(TokenStream $ts) : Result
    {
        try {
            $index = $ts->index();
            if ($this->onTry) ($this->onTry)();
            $result = $this->parser($ts, ...$this->stack);
        }
        catch(Halt $e) {
            $ts->jump($index);

            throw $e;
        }

        if ($result instanceof Ast) {
            if ($this->onCommit) ($this->onCommit)($result);
        }
        else
            $ts->jump($index);

        return $result;
    }

    final function as(string $label) : self
    {
        if( false !== strpos($label, ' '))
            throw new InvalidArgumentException(
                "Parser label cannot contain spaces, '{$label}' given.");

        $this->label = $label;

        return $this;
    }

    final function onTry(callable $fn) : self
    {
        $this->onTry = $fn;

        return $this;
    }

    final function onCommit(callable $fn) : self
    {
        $this->onCommit = $fn;

        return $this;
    }

    final function type() : string
    {
        return $this->type;
    }

    final function is(string $type) : bool
    {
        return $this->type === $type;
    }

    final protected function error(TokenStream $ts)
    {
        return new Error($this->expected(), $ts->current(), $ts->last());
    }
}
