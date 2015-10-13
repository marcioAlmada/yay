<?php declare(strict_types=1);

namespace Yay;

class Ignore extends Directive {

    const
        E_EMPTY_PATTERN = "Empty ignore pattern on line %d.",
        E_IDENTIFIER = "Bad ignore identifier '%s' on line %d."
    ;

    protected
        $ignorable,
        $parsers = []
    ;

    function __construct(int $line, array $ignorables) {
        $this->ignorable = $this->compile($line, ...$ignorables);
    }

    function specificity() : int {
        return count($this->parsers);
    }

    function apply(TokenStream $ts) {
        $result = $this->ignorable->parse($ts);

       if ($result instanceof Ast && ! $result->isEmpty()) $ts->step(-1);
    }

    private function compile(int $line, token ...$ignorables) : parser {
        if (! $ignorables)
            $this->fail(self::E_EMPTY_PATTERN, $line);

        $body = TokenStream::fromSlice($ignorables);

        repeat
        (
            either
            (
                $this->layer('{', '}')
                ,
                $this->layer('[', ']')
                ,
                $this->layer('(', ')')
                ,
                rtoken('/^T_\w+·\w+$/')
                    ->onCommit(function(Ast $result) {
                        $token = $result->token();
                        $type = $this->lookupTokenType($token);
                        $this->parsers[] = token($type)->as((string) $token);
                    })
                ,
                rtoken('/^T_\w+·$/')
                    ->onCommit(function(Ast $result) {
                        $token = $result->token();
                        $type = $this->lookupTokenType($token);
                        $this->parsers[] = token($type);
                    })
                ,
                rtoken('/·/')
                    ->onCommit(function(Ast $result) {
                        $this->fail(self::E_IDENTIFIER, $result->token());
                    })
                ,
                any()
                    ->onCommit(function(Ast $result) {
                        $this->parsers[] = token($result->token());
                    })
            )
        )
        ->parse($body);

        if (! $ignorables)
            $this->fail(self::E_EMPTY_PATTERN, $line);

        return optional(consume(chain(...$this->parsers), CONSUME_DO_TRIM));
    }

    private function layer(string $start, string $end) : parser {
        return
            chain
            (
                token($start)
                ,
                rtoken('/^···(\w+)?$/')
                ,
                commit
                (
                    token($end)
                )
            )
            ->onCommit(function() { $this->parsers[] = braces(); });
    }
}
