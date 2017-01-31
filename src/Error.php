<?php declare(strict_types=1);

namespace Yay;

class Error implements Result {

    const
        DISABLED = false,
        /**
         * Use for deterministic parsing and debug only. Complex errors may
         * increase GC cost a lot when parsing large inputs.
         */
        ENABLED = true
    ;

    const
        UNEXPECTED = "Unexpected %s on line %d, ",
        UNEXPECTED_END = "Unexpected end at %s on line %d, ",
        EXPECTED = "expected %s."
    ;

    protected
        $expected,
        $unexpected,
        $last,
        /**
         * Points to another Error in case there is a list of errors
         *
         * @var self|null
         */
        $and
    ;

    function __construct(Expected $expected = null, Token $unexpected = null, Token $last) {
        $this->expected = $expected;
        $this->unexpected = $unexpected;
        $this->last = $last;
    }

    function with(self $e) {
        $this->and = $e;
    }

    function message() : string {
        $errors = [];
        $error = $this;
        while($error) {
            $unexpected = ($error->unexpected ?: $error->last);
            $prefix = sprintf(
                $error->unexpected ? self::UNEXPECTED : self::UNEXPECTED_END,
                $unexpected->dump(),
                $unexpected->line()
            );
            if (isset($errors[$prefix]))
                $errors[$prefix]->append($error->expected);
            else
                $errors[$prefix] = $error->expected;

            $error = $error->and;
        }

        $messages = [];
        foreach ($errors as $prefix => $expected) {
            $messages[] = $prefix . sprintf(self::EXPECTED, (string) $expected);
        }

        return implode(PHP_EOL, $messages);
    }

    function halt() {
        throw new Halt($this->message());
    }
}
