<?php declare(strict_types=1);

namespace Yay;

class Token implements \JsonSerializable {

    /**
     * pseudo token types
     */
    const
        EOF = 1010,
        ANY = 1021,
        NONE = 1032,
        MATCH = 1043,
        OPERATOR = 1054
    ;

    /**
     * lookup table used to dump pseudo token types
     */
    const TOKENS = [
        self::EOF => 'EOF',
        self::ANY => 'ANY',
        self::NONE => 'NONE',
        self::MATCH => 'MATCH',
        self::OPERATOR => 'OPERATOR'
    ];

    protected
        $type,
        $value,
        $line,
        $literal = false
    ;

    function __construct($type, string $value = null, int $line = null) {
        if (! is_scalar($type))
            throw new YayException("Token type must be int or string.");

        if (is_string($type)) {
            if(1 !== mb_strlen($type))
                throw new YayException("Invalid token type '{$type}'");

            $this->literal = true;
            $value = $type;
        }

        $this->type = $type;
        $this->value = $value;
        $this->line = $line;
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
        return ($this->is($token->type) && $this->contains($token->value));
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

    function literal() : bool {
        return $this->literal;
    }

    static function eof() : self {
        return new self();
    }

    static function any() : self {
        return new self(self::ANY);
    }

    static function none() : self {
        return new self(self::NONE);
    }

    static function match(string $value) : self {
        return new self(self::MATCH, $value);
    }

    static function operator(string $value) : self {
        return new self(self::OPERATOR, $value);
    }

    function jsonSerialize() {
        return $this->__toString();
    }
}
