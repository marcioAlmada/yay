<?php declare(strict_types=1);

namespace Yay;

class Error implements Result {

    const
        UNEXPECTED_TOKEN = "Unexpected %s, expected %s."
    ;

    protected
        $stack = [],
        $unexpected,
        $expected
    ;

    function __construct(Expected $expected, string $unexpected) {
        $this->expected = $expected;
        $this->unexpected = $unexpected;
        $this->push($this);
    }

    function push(self $e) {
        foreach ($e->expected->all() as $token)
            $this->stack[$e->unexpected][] = $token;
    }

    function message() : string {
        $messages = [];
        foreach ($this->stack as $unexpected => $expected) {
            $messages[] = sprintf(
                self::UNEXPECTED_TOKEN,
                $unexpected,
                implode(
                    ' or ',
                    array_unique(
                        array_map(
                            function(Token $t) {
                                return $t->dump();
                            },
                            $expected
                        )
                    )
                )
            );
        }

        return implode(PHP_EOL, $messages);
    }

    function halt() {
        throw new Halt($this->message());
    }
}
