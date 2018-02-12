<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

use Yay\ParserTracer\{ParserTracer, NullParserTracer};

/**
 * This might need an interface
 */
abstract class Parser {

    protected static
        $tracer
    ;

    protected
        $type,
        $label,
        $stack,
        $onCommit,
        $errorLevel = Error::DISABLED,
        $optimized = false
    ;

    abstract function expected() : Expected;

    abstract function isFallible() : bool;

    function __construct(string $type, ...$stack)
    {
        self::$tracer ?: self::$tracer = new NullParserTracer;

        $this->type = $type;
        $this->stack = $stack;
        $this->withErrorLevel($this->errorLevel);
    }

    final function __toString() : string
    {
        return $this->type . ($this->label !== null ? " as {$this->label}" : '');
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
        try {
            self::$tracer->push($this);

            $index = $ts->index();

            self::$tracer->trace($index, 'attempt');

            $result = $this->parser($ts, ...$this->stack);

            if ($result instanceof Ast) {
                self::$tracer->trace($index, 'production', implode('', $result->tokens()));

                if (null !== $this->onCommit) ($this->onCommit)($result);
            }
            else {
                $ts->jump($index);
                self::$tracer->trace($index, 'error');
            }
        }
        catch(Halt $e) {
            $ts->jump($index);
            self::$tracer->trace($index, 'error');

            throw $e;
        }
        finally {
            self::$tracer->pop($this);
        }

        return $result;
    }

    function optimize() : self
    {
        if (false === $this->optimized) {
            $this->type = '*' . $this->type;
            $this->optimized = true;
            array_walk_recursive($this->stack, function(&$parser) {
                if ($parser instanceof self) $parser = $parser->optimize();
            });
        }

        return $this;
    }

    final function as($label) : self
    {
        if ('' !== (string) $label) {
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

    final function withErrorLevel(bool $errorLevel) : self
    {
        if ($this->errorLevel !== $errorLevel) {
            $this->errorLevel = $errorLevel;

            if ($this->stack) {
                array_walk_recursive($this->stack, function($substack){
                    if ($substack instanceof self) $substack->withErrorLevel($this->errorLevel);
                });
            }
        }

        return $this;
    }

    final function error(TokenStream $ts, Expected $expected = null) /*: Error|null*/
    {
        if ($this->errorLevel === Error::ENABLED)
            return new Error($expected ?: $this->expected(), $ts->current(), $ts->last());
    }

    final static function setTracer(ParserTracer $tracer)
    {
        self::$tracer = $tracer;
    }
}
