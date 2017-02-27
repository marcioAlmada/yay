<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException,
    ArrayIterator,
    TypeError
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
            throw new TypeError('Unmerged AST.');

        $this->ast = $ast;
        $this->label = $label;
    }

    function __get($path) {
        return $this->get($path);
    }

    function get($strPath) {
        $ret = null;
        $path = preg_split('/\s+/', $strPath);

        if ($wrap = ('*' === $path[0])) {
            array_shift($path);
        }

        try {
            $ret = $this->getIn($this->ast, $path);

            if (null === $ret && $this->parent) $ret = $this->parent->get($strPath);

            if ($wrap) {
                $label = end($path) ?: null;
                $ret = new self($label, $ret);
            }
        }
        catch(TypeError $e) {
            if ($wrap) {
                throw new \Yay\YayException("Could not access (Ast)->{'" . implode(' ', $path) . "'}.");
            }
        }

        return $ret;
    }

    function unwrap() {
        return $this->ast;
    }

    function token() {
        if ($this->ast instanceof Token) return $this->ast;

        $this->failCasting(Token::class);
    }

    function null() {
        if (\is_null($this->ast)) return $this->ast;

        $this->failCasting('null');
    }

    function bool() {
        if (\is_bool($this->ast)) return $this->ast;

        $this->failCasting('boolean');
    }

    function string() {
        if (\is_string($this->ast)) return $this->ast;

        $this->failCasting('string');
    }


    function array() {
        if (\is_array($this->ast)) return $this->ast;

        $this->failCasting('array');
    }

    function list() {
        $array = $this->array();

        reset($array);

        $isAssociative = \count(array_filter(array_keys($array), 'is_string')) > 0;

        foreach ($array as $label => $value)
            yield new Ast(($isAssociative ? $label : null), $value);
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
        return !\count($this->ast) || null === $this->ast;
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
        return \is_array($this->ast) ? \array_keys($this->ast) : [];
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
        if (\count($keys) === 1 && isset($array[$keys[0]])) {
            return $array[$keys[0]];
        }

        $current = $array;
        foreach ($keys as $key) {
            if (!\is_array($current) || !\array_key_exists($key, $current)) {
                return $default;
            }

            $current = $current[$key];
        }

        return $current;
    }

    private function failCasting(string $type) {
        throw new YayException(sprintf("Ast cannot be casted to '%s'", $type));
    }
}
