<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

/**
 * This might need an interface
 */
abstract class Parser {

    const
        E_DISABLE = 0,
        E_ENABLE  = 1,
        /**
         * Use for deterministic parsing and debug only. Complex errors may
         * increase GC cost a lot when parsing large inputs.
         */
        E_ALWAYS  = 2
    ;

    protected static
        $errorLevel = self::E_DISABLE
    ;

    protected
        $type,
        $label,
        $stack,
        $onCommit
    ;

    abstract function expected() : Expected;

    abstract function isFallible() : bool;

    function __construct(string $type, ...$stack)
    {
        $this->type = $type;
        $this->stack = $stack;
    }

    final function __toString() : string {
        return $this->type;
    }

    final function __debugInfo()
    {
        return [$this->type, $this->label, $this->stack];
    }

    final function __clone()
    {
        $this->onCommit = null;
    }

    function parse(TokenStream $ts) /*: Result|null*/
    {
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

    final function as(string $label) : self
    {
        if( false !== strpos($label, ' '))
            throw new InvalidArgumentException(
                "Parser label cannot contain spaces, '{$label}' given.");

        $this->label = $label;

        return $this;
    }

    final function onCommit(callable $fn) : self
    {
        $this->onCommit = $fn;

        return $this;
    }

    final protected function error(TokenStream $ts) /*: Error|null*/
    {
        if (self::$errorLevel > 0)
            return new Error($this->expected(), $ts->current(), $ts->last());
    }

    public static function errorLevel(int $flag) : int {
        $previous = self::$errorLevel;
        if (self::$errorLevel < 2) self::$errorLevel = $flag;

        return $previous;
    }
}
