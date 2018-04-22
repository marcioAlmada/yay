<?php declare(strict_types=1);

namespace Yay;

class AnonymousFunction {
    protected
        $closure
    ;

    function __construct(Ast $ast) {
        if (! $ast->isEmpty()) $this->closure = $this->compileAnonymousFunctionArg($ast);
    }

    function __invoke(...$args) {
        if ($this->closure) return ($this->closure)(...$args);
    }

    private function compileAnonymousFunctionArg(Ast $ast) : \Closure {
        $arglist = $ast->{'* args'}->implode();
        $body = $ast->{'* body'}->implode();
        $source = "<?php\nreturn static function({$arglist}){\n{$body}\n};";
        $file = sys_get_temp_dir() . '/yay-function-' . sha1($source);

        if (!is_readable($file)) file_put_contents($file, $source);

        return include $file;
    }
}
