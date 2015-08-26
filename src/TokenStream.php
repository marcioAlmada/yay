<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

class TokenStream {

    const
        DEFAULT_INDEX = 0
        ,
        SKIPPABLE = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]
    ;

    protected
        $index = self::DEFAULT_INDEX
        ,
        $tokens = []
    ;

    private function __construct() {}

    function __toString() : string {
        return implode('', $this->tokens);
    }

    function __clone() {
        $this->tokens = array_map(
            function($t) { return clone $t; }, $this->tokens);
    }

    function index() : int {
        return $this->index;
    }

    function jump(int $index) {
        $this->index = $index;
    }

    function reset() {
        $this->jump(self::DEFAULT_INDEX);
    }

    function current() /* : Token|null */ {
        return $this->tokens[$this->index()] ?? null;
    }

    function step($step = 1) /* : Token|null */ {
        $this->jump($this->index() + $step);

        return $this->current();
    }

    function skip(...$types) /* : int|char[1] */ {
        while (($t = $this->current()) && $t->is(...$types)) $this->step();

        return $this->current();
    }

    function unskip(...$types) /* : int|char[1] */ {
        while (($t = $this->step(-1)) && $t->is(...self::SKIPPABLE));
        $this->step();

        return $this->current();
    }

    function next() /* : Token|null */ {
        $this->step();
        $this->skip(...self::SKIPPABLE);

        return $this->current();
    }

    function last() : Token {
        return end($this->tokens);
    }

    function trim() {
        while (reset($this->tokens)->is(T_WHITESPACE)) array_shift($this->tokens);
        while (end($this->tokens)->is(T_WHITESPACE)) array_pop($this->tokens);
    }

    function extract(int $from, int $to) : self {
        if ($from < 0 || $to <= $from)
            throw new InvalidArgumentException(
                "Invalid stream interval {$from}...{$this->index()}");

        $this->jump($from);

        return self::fromSlice(
            array_splice($this->tokens, $from, ($to - $from)));
    }

    function inject(self $tokens) {
        if ($tokens->tokens)
            array_splice($this->tokens, $this->index(), 0, $tokens->tokens);
    }

    function append(self $ts) {
        $this->push(...$ts->tokens);
    }

    function push(token ...$tokens) {
        foreach ($tokens as $token) $this->tokens[] = $token;
    }

    static function empty() : self {
        return new self;
    }

    static function fromSource(string $source) : self {
        $line = 0;
        $tokens = token_get_all($source);

        foreach ($tokens as $i => $token) // normalize line numbers
            if(is_array($token))
                $line = $token[2];
            else
                $tokens[$i] = [$token, $token, $line];

        return self::fromSequence(...$tokens);
    }

    static function fromSequence(...$tokens) : self {
        foreach ($tokens as $i => $t)
            $tokens[$i] = ($t instanceof Token) ? clone $t : new Token(...$t);

        return self::fromSlice($tokens);
    }

    static function fromSlice(array $tokens) : self {
        if(! $tokens)
            throw new InvalidArgumentException("Empty token stream.");

        $ts = new self();
        $ts->tokens = (function(token ...$tokens) {
            return $tokens;
        })(...$tokens);

        return $ts;
    }
}
