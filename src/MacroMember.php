<?php declare(strict_types=1);

namespace Yay;

abstract class MacroMember {

    const
        PRETTY_PRINT =
                JSON_PRETTY_PRINT
            |   JSON_BIGINT_AS_STRING
            |   JSON_UNESCAPED_UNICODE
            |   JSON_UNESCAPED_SLASHES
    ;

    protected function fail(string $error, ...$args) {
        throw new YayParseError(sprintf($error, ...$args));
    }

    protected function failRuntime(string $error, ...$args) {
        throw new YayRuntimeException(sprintf($error, ...$args));
    }

    protected function compileCallable(string $namespace, Ast $type, string $error): callable {
        $name = $function = $type->implode();

        if (0 !== strpos($function, '\\')) $function = $namespace . $function;

        if (! function_exists($function)) {
            $tokens = $type->tokens();
            $this->fail(
                $error,
                $name,
                $tokens[0] != '\\' ? $tokens[0]->line() : $tokens[1]->line()
            );
        }

        return $function;
    }
}
