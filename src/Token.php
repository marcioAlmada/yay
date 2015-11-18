<?php declare(strict_types=1);

namespace Yay;

class Token implements \JsonSerializable {

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
        $literal = false,
        $context
    ;

    function __construct($type, string $value = null, int $line = null) {
        assert(null === $this->type, "Attempt to modify immutable token.");

        assert(is_int($type)||is_string($type), "Token type must be int or string.");

        if (is_string($type)) {
            if(1 !== mb_strlen($type))
                throw new YayException("Invalid token type '{$type}'");

            $this->literal = true;
            $value = $type;
        }

        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
        $this->context = new BlueContext;
    }

    function __clone() {
        $this->context = clone $this->context;
    }

    function __toString(): string {
        return (string) $this->value;
    }

    function __debugInfo() {
        return [$this->dump()];
    }

    function dump(): string {
        $name = $this->name();

        return $this->literal ? "'{$name}'" : "{$name}({$this->value})";
    }

    function is(/* string|int */ ...$types): bool {
        return in_array($this->type, $types);
    }

    function contains($value): bool {
        return $value === null ?: $this->value === $value;
    }

    function equals(self $token): bool {
        return
            // inlined $this->is()
            ($this->type === $token->type &&
                // inlined $this->contains()
                ($token->value === null ?: $this->value === $token->value));
    }

    function name(): string {
        return
            ($this->literal)
                ? $this->type
                : self::TOKENS[$this->type] ?? token_name($this->type)
        ;
    }

    function type() /* : string|int */ {
        return $this->type;
    }

    function line(): int {
        return $this->line ?: 0;
    }

    function context() : BlueContext {
        return $this->context;
    }

    function jsonSerialize() {
        return $this->__toString();
    }
}
