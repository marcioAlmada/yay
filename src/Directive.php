<?php declare(strict_types=1);

namespace Yay;

/**
 * This needs an interface
 */
abstract class Directive {

    const
        E_TOKEN_TYPE = "Undefined token type '%s' on line %d."
    ;

    protected $id;

    abstract function specificity() : int;

    abstract function apply(TokenStream $TokenStream);

    function __construct() {
        static $id = 0;

        $this->id = $id++;
    }

    final function id() : int {
        return $this->id;
    }

    protected function lookupTokenType(Token $token) : int {
        $type = explode('·', (string) $token)[0];
        if (! defined($type))
            $this->fail(self::E_TOKEN_TYPE, $type, $token->line());

        return constant($type);
    }

    final protected function fail(string $error, ...$args) {
        throw new YayException(sprintf($error, ...$args));
    }
}
