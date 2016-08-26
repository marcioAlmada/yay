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

    function __clone() {
        $tokens = [];
        $node = $this->first->next;
        while ($node instanceof Node) {
            $tokens[] = $node->token;
            $node = $node->next;
        }

        $ts = self::fromSlice($tokens);
        $this->first = $ts->first;
        $this->last = $ts->last;
        $this->reset();
    }

    function index() /* : Node|null */ { return $this->current; }

    function jump(Index $index) /* : void */ { $this->current = $index; }

    function reset() /* : void */ { $this->jump($this->first->next); }

    function current() /* : Token|null */ {
        if ($this->current instanceof NodeStart) $this->reset();

        return $this->current->token;
    }

    function step() /* : Token|null */ {
        if (!($this->current instanceof NodeEnd)) $this->current = $this->current->next;

        return $this->current();
    }

    function back() /* : Token|null */ {
        if (!($this->current instanceof NodeStart)) $this->current = $this->current->previous;

        return $this->current();
    }

    function skip(int ...$types) /* : Token|null */ {
        while (null !== ($t = $this->current()) && $t->is(...$types)) $this->step();

        return $this->current();
    }

    function unskip(int ...$types) /* : Token|null */ {
        while (null !== ($t = $this->back()) && $t->is(...$types));
        $this->step();

        return $this->current();
    }

    function next() /* : Token|null */ {
        $this->step();
        $this->skip(...self::SKIPPABLE);

        return $this->current();
    }

    function previous() /* : Token|null */ {
        $this->unskip(...self::SKIPPABLE);
        $this->back();

        return $this->current();
    }

    function last() : Token {
        return $this->last->previous->token;
    }

    function first() : Token {
        return $this->first->next->token;
    }

    function trim() {
        while (null !== ($t = $this->first->next->token) && $t->is(T_WHITESPACE)) $this->shift();
        while (null !== ($t = $this->last->previous->token) && $t->is(T_WHITESPACE)) $this->pop();
        $this->reset();
    }

    function shift() {
        $this->first->next = $this->first->next->next;
        $this->first->next->previous = $this->first;
    }

    function pop() {
        $this->last->previous = $this->last->previous->previous;
        $this->last->previous->next = $this->last;
    }

    function extract(Index $from, Index $to) {
        assert($from !== $to);
        assert(! $this->isEmpty());

        $from = $from->previous;
        self::link($from, $to);
        $this->jump($from);
    }

    function inject(self $ts) {
        if ($ts->isEmpty()) return;

        if ($this->current instanceof NodeEnd) $this->jump($this->last->previous);

        $a = $this->isEmpty() ? $this->first : $this->current;
        $b = $ts->first->next;
        $e = $ts->last->previous;
        $f = $this->isEmpty() ? $this->last : $this->current->next;

        self::link($a, $b);
        self::link($e, $f);
    }

    function push(Token $token) {
        $a = $this->last->previous;
        $b = new Node($token);
        $c = $this->last;

        self::link($a, $b);
        self::link($b, $c);
    }

    function isEmpty() : bool {
        return
            ($this->first->next instanceof NodeEnd)
                && ($this->last->previous instanceof NodeStart)
        ;
    }


    static function fromSourceWithoutOpenTag(string $source) : self {
        $ts = self::fromSource('<?php ' . $source);
        $ts->shift();

        return $ts;
    }

    static function fromSource(string $source) : self {
        $line = 0;
        $tokens = token_get_all($source);

        foreach ($tokens as $i => $token) // normalize line numbers
            if (is_array($token))
                $line = $token[2];
            else
                $tokens[$i] = [$token, $token, $line];

        return self::fromSequence(...$tokens);
    }

    static function fromSequence(...$tokens) : self {
        foreach ($tokens as $i => $t)
            $tokens[$i] = ($t instanceof Token) ? $t : new Token(...$t);

        return self::fromSlice($tokens);
    }

    static function fromSlice(array $tokens) : self {
        $ts = new self;
        $ts->first = new NodeStart;
        $ts->last = new NodeEnd;
        self::link($ts->first, $ts->last);

        foreach ($tokens as $token) $ts->push($token);

        $ts->reset();

        return $ts;
    }

    private function notImplemented(string $method){
        throw new \Exception("{$method} not implemented.");
    }

    private static function link($a, $b) {
        $a->next =  $b;
        $b->previous = $a;
    }
}
