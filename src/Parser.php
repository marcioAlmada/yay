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
        $onCommit,
        $onTry,
        $errorLevel = Error::DISABLED
    ;

    abstract function expected() : Expected;

    abstract function isFallible() : bool;

    function __construct(string $type, ...$stack)
    {
        $this->type = $type;
        $this->stack = $stack;
        $this->withErrorLevel($this->errorLevel);
    }

    final function __toString() : string
    {
        return $this->type;
    }

    final function __debugInfo()
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'stack' => $this->stack,
        ];
    }

    final function __clone()
    {
        $this->onCommit = null;
    }

    function parse(TokenStream $ts) /*: Result|null*/
    {
        if (null !== $this->onTry) ($this->onTry)();

        try {
            $index = $ts->index();
            $result = $this->parser($ts, ...$this->stack);
        }
        catch(Halt $e) {
            $ts->jump($index);

            throw $e;
        }

        if ($result instanceof Ast) {
            if (null !== $this->onCommit) ($this->onCommit)($result);
        }
        else
            $ts->jump($index);

        return $result;
    }

    final function as(/*string|null*/ $label) : self
    {
        if (null !== $label) {
            if(false !== strpos($label, ' '))
                throw new InvalidArgumentException(
                    "Parser label cannot contain spaces, '{$label}' given.");

            $this->label = $label;
        }

        return $this;
    }

    final function onCommit(callable $fn) : self
    {
        $this->onCommit = $fn;

        return $this;
    }

    final function onTry(callable $fn) : self
    {
        $this->onTry = $fn;

        return $this;
    }

    final function withErrorLevel(bool $errorLevel) : self
    {
        if ($this->errorLevel !== $errorLevel) {
            $this->errorLevel = $errorLevel;
            foreach ($this->stack as $substack) {
                if ($substack instanceof self) {
                    $substack->{__FUNCTION__}($this->errorLevel);
                }
            }
        }

        return $this;
    }

    final protected function error(TokenStream $ts) /*: Error|null*/
    {
        if ($this->errorLevel === Error::ENABLED)
            return new Error($this->expected(), $ts->current(), $ts->last());
    }

}
