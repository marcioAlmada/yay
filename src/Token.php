<?php declare(strict_types=1);

namespace Yay;

class Token implements \JsonSerializable {

    const SKIPPABLE = [
        T_WHITESPACE => true,
        T_COMMENT => true,
        T_DOC_COMMENT => true,
    ];

    /**
     * pseudo token types
     */
    const
        ANY = 1021,
        NONE = 1032,
        MATCH = 1043,
        OPERATOR = 1054,
        CLOAKED = 1065
    ;

    /**
     * lookup table used to dump pseudo token types
     */
    const TOKENS = [
        self::ANY => 'ANY',
        self::NONE => 'NONE',
        self::MATCH => 'MATCH',
        self::OPERATOR => 'OPERATOR',
        self::CLOAKED => 'CLOAKED'
    ];

    protected
        $type,
        $value,
        $line,
        $skippable = false
    ;

    private
        $id
    ;

    protected static $_id = 0;

    function __construct($type, $value = null, $line = null) {

        assert(null === $this->type, "Attempt to modify immutable token.");

        $this->id = (__CLASS__)::$_id++;

        if (\is_string($type)) {
            $this->value = $type;
        }
        else {
            $this->skippable = isset((__CLASS__)::SKIPPABLE[$type]);
            $this->value = $value;
        }

        $this->type = $type;
        $this->line = $line;

        assert($this->check());
    }

    function __toString() {
        return (string) $this->value;
    }

    function __debugInfo() {
        return [$this->dump()];
    }

    function dump(): string {
        $name = $this->name();

        return $this->type === $this->value ? "'{$name}'" : "{$name}({$this->value})";
    }

    function is($type) {
        return $this->type === $type;
    }

    function equals(self $token) {
        return
            ($this->type === $token->type &&
                ($this->value === $token->value ?:
                    ($token->value === null ?: $this->value === null)));
    }

    function isSkippable() {
        return $this->skippable;
    }

    function name(): string {
        return
            ($this->type === $this->value)
                ? $this->type
                : (__CLASS__)::TOKENS[$this->type] ?? \token_name($this->type)
        ;
    }

    function type() /* : string|int */ {
        return $this->type;
    }

    function value() {
        return $this->value;
    }

    function line() {
        return $this->line;
    }

    function id() {
        return $this->id;
    }

    function jsonSerialize() {
        return (string) $this;
    }

    private function check() {
        assert(\is_int($this->id));
        assert(\is_bool($this->skippable));
        assert(\is_int($this->type) || (\is_string($this->type) && \strlen($this->type) === 1), "Token type must be int or string[0].");
        assert(\is_string($this->value) || (\is_null($this->value)), "Token value must be string or null.");
        assert(\is_int($this->line) || (\is_null($this->line)), "Token line must be int or null.");

        return true;
    }
}
