<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

class TokenStream {

    protected
        $first,
        $current,
        $last
    ;

    private function __construct() {}

    function __toString() : string {
        // use a 'friend' class for Token and get all token values in a string
        return (new class extends Token {
            function __construct() {}
            function toSource(NodeStart $node) : string {
                $str = '';
                $node = $node->next;

                while ($node instanceof Node) {
                    $str .= $node->token->value;
                    $node = $node->next;
                }

                return $str;
            }
        })->toSource($this->first);
    }

    function debug() : string {
        return (new class extends Token {
            function __construct() {}
            function toSource(NodeStart $node, Index $current) : string {
                $str = '';
                $node = $node->next;

                while ($node instanceof Node) {
                    if ($node === $current) $str .= "\033[1;37;40m";
                    $str .= $node->token->value;
                    if ($node === $current) $str .= "\033[0m";
                    $node = $node->next;
                }

                return $str;
            }
        })->toSource($this->first, $this->current);
    }

    function __clone() {
        $first = new NodeStart;
        $last = new NodeEnd;

        $first->next = $last;
        $last->previous = $first;

        $current = $first;
        $old = $this->first->next;
        while ($old instanceof Node) {
            $node = new Node($old->token);
            $current->next = $node;
            $node->previous = $current;
            $current = $node;
            $old = $old->next;
        }

        $current->next = $last;
        $last->previous = $current;

        $this->first = $first;
        $this->last = $last;
        $this->current = $this->first->next;
    }

    function index() /* : Node|null */ { return $this->current; }

    function jump($index) /* : void */ {
        assert($index instanceof Index);

        if ($index instanceof NodeStart)
            $this->current = $this->first->next;
        else
            $this->current = $index;
    }

    function reset() /* : void */ {
        $this->current = $this->first->next;
    }

    function current() /* : Token|null */ {
        return $this->current->token;
    }

    function step() /* : Token|null */ {
        $this->current = $this->current->next;

        return $this->current->token;
    }

    function back() /* : Token|null */ {
        $this->current = $this->current->previous;

        return $this->current->token;
    }

    function skip() /* : Token|null */ {
        while ($this->current->skippable) {
            $this->current = $this->current->next;
        }

        return $this->current->token;
    }

    function unskip() /* : Token|null */ {
        $this->current = $this->current->previous;

        while ($this->current->skippable) {
            $this->current = $this->current->previous;
        }

        $this->current = $this->current->next;

        return $this->current->token;
    }

    function next() /* : Token|null */ {
        $this->current = $this->current->next;

        while ($this->current->skippable) {
            $this->current = $this->current->next;
        }

        return $this->current->token;
    }

    function previous() /* : Token|null */ {
        $this->current = $this->current->previous;

        while ($this->current->skippable) {
            $this->current = $this->current->previous;
        }

        return $this->current->token;
    }

    function last() : Token {
        return $this->last->previous->token;
    }

    function first() : Token {
        return $this->first->next->token;
    }

    function trim() {
        while (null !== ($t = $this->first->next->token) && $t->is(T_WHITESPACE)) {
            $this->first->next = $this->first->next->next;
            $this->first->next->previous = $this->first;
        }
        while (null !== ($t = $this->last->previous->token) && $t->is(T_WHITESPACE)) {
            $this->last->previous = $this->last->previous->previous;
            $this->last->previous->next = $this->last;
        }
        $this->current = $this->first->next;
    }

    function shift() {
        $this->first->next = $this->first->next->next;
        $this->first->next->previous = $this->first;
    }

    function pop() {
        $this->last->previous = $this->last->previous->previous;
        $this->last->previous->next = $this->last;
    }

    function extract($from, $to) {
        assert($from instanceof Index);
        assert($to instanceof Index);

        assert($from !== $to);
        assert(! $this->isEmpty());

        $from = $from->previous;
        $from->next = $to;
        $to->previous = $from;

        $this->current = $from;
    }

    function inject($ts) {
        assert($ts instanceof self);

        if (($ts->first->next instanceof NodeEnd)
                && ($ts->last->previous instanceof NodeStart)) return;

        if ($this->current instanceof NodeEnd) $this->current = $this->last->previous;

        $a = $this->current;
        $b = $ts->first->next;
        $e = $ts->last->previous;
        $f = $this->current->next;

        $a->next = $b;
        $b->previous = $a;

        $e->next = $f;
        $f->previous = $e;
    }

    function push($token) {
        assert($token instanceof Token);

        $a = $this->last->previous;
        $b = new Node($token);
        $c = $this->last;

        $a->next = $b;
        $b->previous = $a;

        $b->next = $c;
        $c->previous = $b;
    }

    function isEmpty() : bool {
        return
            ($this->first->next instanceof NodeEnd)
                && ($this->last->previous instanceof NodeStart)
        ;
    }

    static function fromSourceWithoutOpenTag(string $source) : self {
        $ts = self::fromSource('<?php ' . $source);
        $ts->first->next = $ts->first->next->next;
        $ts->first->next->previous = $ts->first;
        $ts->reset();

        return $ts;
    }

    static function fromSource(string $source) : self {
        $tokens = \token_get_all($source);

        $ts = new self;
        $first = new NodeStart;
        $last = new NodeEnd;

        $first->next = $last;
        $last->previous = $first;

        $line = 0;
        $current = $first;
        $realign = []; // tokenizer omits line number sometimes so we borrow from next non whitespace
        foreach ($tokens as $t){
            if (\is_array($t)) {
                $line = $t[2];
                $token = new Token(...$t);

                if (T_WHITESPACE !== $token->type()) {
                    foreach ($realign as $node)
                        $node->token = new Token($node->token->type(), $node->token->value(), $line);

                    $realign = [];
                }
            }
            else {
                $token = new Token($t, $t, $line);
            }

            $node = new Node($token);
            $current->next = $node;
            $node->previous = $current;

            $current = $node;

            if (is_string($current->token->type())) $realign[] = $current;
        }

        $current->next = $last;
        $last->previous = $current;

        $ts->first = $first;
        $ts->last = $last;

        $ts->current = $ts->first->next;

        return $ts;
    }

    static function fromSlice(array $tokens) : self {
        $ts = new self;
        $first = new NodeStart;
        $last = new NodeEnd;

        $first->next = $last;
        $last->previous = $first;

        $current = $first;
        foreach ($tokens as $token){
            $node = new Node($token);
            $current->next = $node;
            $node->previous = $current;

            $current = $node;
        }

        $current->next = $last;
        $last->previous = $current;

        $ts->first = $first;
        $ts->last = $last;

        $ts->current = $ts->first->next;

        return $ts;
    }

    static function fromSequence(Token ...$tokens) : self {
        return self::fromSlice($tokens);
    }
}
