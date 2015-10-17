<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

class TokenStream {

    const
        SKIPPABLE = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]
    ;

    protected
        $first,
        $current,
        $last
    ;

    private function __construct() {}

    function __toString() : string {
        // return (string) $this->first;
        // ↑↑↑ this could be simpler, but recursion exceeds stack frame size :(
        // ↓↓↓ so instead, we collect all tokens and implode
        $tokens = [];
        $node = $this->first;
        while($node) {
            $tokens[] = $node->token;
            $node = $node->next;
        }

        return implode('', $tokens);
    }

    function __clone() {
        $node = $this->first;
        $first = $last = new Node(clone $node->token);

        while($node = $node->next) {
            $last->next = new Node(clone $node->token);
            $last->next->previous = $last;
            $last = $last->next;
        }

        $this->first = $first;
        $this->last = $last;
        $this->reset();
    }

    function index() /* : Node|null */ { return $this->current; }

    function jump($node) /* : void */ { $this->current = $node; }

    function reset() /* : void */ { $this->jump($this->first); }

    function current() /* : Token|null */ {
        return $this->current ? $this->current->token : null;
    }

    function step() /* : Token|null */ {
        if ($this->current)
            $this->current = $this->current->next;
        else
            $this->current = $this->first;

        return $this->current();
    }

    function back() /* : Token|null */ {
        if ($this->current)
            $this->current = $this->current->previous;
        else
            $this->current = $this->last;

        return $this->current();
    }

    function skip(int ...$types) /* : Token|null */ {
        while (($t = $this->current()) && $t->is(...$types)) $this->step();

        return $this->current();
    }

    function unskip(int ...$types) /* : Token|null */ {
        while (($t = $this->back()) && $t->is(...$types));
        $this->step();

        return $this->current();
    }

    function next() /* : Token|null */ {
        $this->step();
        $this->skip(...self::SKIPPABLE);

        return $this->current();
    }

    function last() : Token {
        return $this->last->token;
    }

    function first() : Token {
        return $this->first->token;
    }

    function trim() {
        while ($this->first && $this->first->token->is(T_WHITESPACE)) $this->shift();
        while ($this->last && $this->last->token->is(T_WHITESPACE)) $this->pop();
    }

    function extract(Node $from, Node $to = null) {
        if (! $from->previous) {
            $from->previous = new Node(new Token(T_WHITESPACE, '', $from->token->line()));
            $from->previous->next = $from;
            $this->first = $from->previous;
        }

        $this->jump($from->previous);

        while ($from !== $to) {
            if ($from->previous === null)
               $this->first = $from->next;
           else
               $from->previous->next = $from->next;

           if ($from->next === null)
               $this->last = $from->previous;
           else
               $from->next->previous = $from->previous;

            $from = $from->next;
        }
    }

    function inject(self $tstream) {
        if (!$tstream->first && !$tstream->last) return;

        if (! $this->isEmpty()){
            if ($this->current) {
                $next = $this->current->next;
                $this->current->next = $tstream->first;
                $tstream->first->previous = $this->current;
                if ($next) {
                    $tstream->last->next = $next;
                    $next->previous = $tstream->last;
                }
                else {
                    $this->last = $tstream->last;
                }
            }
            else {
                $this->first->previous = $tstream->last;
                $tstream->last->next = $this->first;
                $this->first = $tstream->first;
            }
        }
        else {
            $this->first = $tstream->first;
            $this->last = $tstream->last;
            $this->current = null;
        }
    }

    function push(Token $token) {
        $node = new Node($token);

        if ($this->last) {
            $node->previous = $this->last;
            $this->last->next = $node;
            $this->last = $this->last->next;
        }
        else $this->current = $this->first = $this->last = $node;
    }

    function shift() {
        if (! $this->first)
            throw new YayException("Empty token stream.");

        $this->first = $this->first->next;

        if ($this->first)
            $this->first->previous = null;
        else
            $this->last = null;
    }

    function isEmpty() : bool {
        return ! ($this->first && $this->last);
    }

    private function pop() {
        $this->last = $this->last->previous;

        if ($this->last)
            $this->last->next = null;
        else
            $this->first = null;
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
        if (! $tokens)
            throw new InvalidArgumentException("Empty token slice.");

        $ts = self::fromEmpty();
        foreach ($tokens as $token) $ts->push($token);

        return $ts;
    }

    static function fromEmpty() : self { return new self; }
}
