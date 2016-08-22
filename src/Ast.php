<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException
;

/**
 * Worst class ever. This needs to be replaced by a SyntaxObject or sort of
 */
class Ast implements Result, Context {

    protected
        $label = null,
        $ast = []
    ;

    private
        $parent
    ;

    function __construct(string $label = null, $ast = []) {
        if ($ast instanceof self)
            throw new InvalidArgumentException('Unmerged AST.');

        $this->ast = $ast;
        $this->label = $label;
    }

    function __get($path) {
        return $this->get($path);
    }

    function get($path) {
        $ret =
            $this->getIn(
                (null !== $this->label ? $this->all() : $this->ast),
                preg_split('/\s+/', $path)
            )
        ;

        if (null === $ret && $this->parent) $ret = $this->parent->get($path);

        return $ret;
    }

    function raw() {
        return $this->ast;
    }

    function token() : Token {
        return $this->ast;
    }

    function array() : array {
        return $this->ast;
    }

    function all() {
        return [($this->label ?? 0) => $this->ast];
    }

    function append(self $ast) : self {
        if (null !== $ast->label) {
            if (isset($this->ast[$ast->label]))
                throw new InvalidArgumentException(
                    "Duplicated AST label '{$ast->label}'.");

            $this->ast[$ast->label] = $ast->ast;
        }
        else $this->ast[] = $ast->ast;

        return $this;
    }

    function push(self $ast) : self {
        $this->ast[] = $ast->label ? [$ast->label => $ast->ast] : $ast->ast;

        return $this;
    }

    function isEmpty() : bool {
        return ! count($this->ast);
    }

    function as(/*string|null*/ $label = null) : self {
        if (null !== $label && null === $this->label) $this->label = $label;

        return $this;
    }

    function withParent(self $parent) : self {
        $this->parent = $parent;

        return $this;
    }

    function symbols() : array {
        return array_keys($this->all()[0]);
    }

    /**
     * Stolen from igorw/get-in because YAY can't have a lot of dependencies
     */
    private function getIn(array $array, array $keys, $default = null)
    {
        if (!$keys) {
            return $array;
        }

        // This is a micro-optimization, it is fast for non-nested keys, but fails for null values
        if (count($keys) === 1 && isset($array[$keys[0]])) {
            return $array[$keys[0]];
        }

        $current = $array;
        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }
}
