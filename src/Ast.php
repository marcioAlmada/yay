<?php declare(strict_types=1);

namespace Yay;

use
    InvalidArgumentException,
    ArrayIterator,
    TypeError
;

class Ast implements Result {

    protected
        $label = '',
        $ast = []
    ;

    private
        $meta
    ;

    function __construct(string $label = '', $ast = []) {
        if ($ast instanceof self)
            throw new InvalidArgumentException('Unmerged AST.');

        $this->ast = $ast;
        $this->label = $label;
    }

    function __set($path, $value) {
        return $this->set($path, $value);
    }

    function set($strPath, $value) {
        $keys = preg_split('/\s+/', $strPath);

        if ([] === $keys) return;

        $current = &$this->ast;
        foreach ($keys as $key) {
            if (!is_array($current)) $current = [];
            $current = &$current[$key];
        }
        $current = $value;
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

        $ret = $this->getIn((array) $this->ast, $path);

        if ($wrap) {
            $label = end($path) ?: '';
            $ret = new self($label, $ret instanceof Ast ? $ret->unwrap() : $ret);
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

    function tokens() {
        $tokens = [];
        $exposed = [];

        if (\is_array($this->ast)) $exposed = $this->ast;
        else $exposed = [$this->ast];

        array_walk_recursive(
            $exposed,
            function($i) use(&$tokens){
                if($i instanceof Token) $tokens[] = $i;
                elseif ($i instanceof self) $tokens = array_merge($tokens, $i->tokens());
            }
        );

        return $tokens;
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
        foreach (array_keys($this->array()) as $index) yield $index => $this->{"* {$index}"};
    }

    function flatten() : self {
        return new self($this->label, $this->tokens());
    }

    function append(self $ast) : self {
        if ('' !== $ast->label) {
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
        return null === $this->ast || [] === $this->ast;
    }

    function as(string $label = '') : Result {
        if ('' !== $label) $this->label = $label;

        return $this;
    }

    function label() {
        return $this->label;
    }

    function withMeta(Map $meta) : Result {
        $this->meta = $meta;

        return $this;
    }

    function meta() : Map {
        return $this->meta ?: Map::fromEmpty();
    }


    function symbols() : array {
        return \is_array($this->ast) ? \array_keys($this->ast) : [];
    }

    function implode(string $glue = '') : string {
        return implode($glue, $this->tokens());
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
