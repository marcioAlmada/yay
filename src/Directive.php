<?php declare(strict_types=1);

namespace Yay;

/**
 * This needs an interface
 */
abstract class Directive {

    const
        E_TOKEN_TYPE = "Undefined token type '%s' on line %d."
    ;

    abstract function specificity() : int;

    abstract function apply(TokenStream $TokenStream);

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
