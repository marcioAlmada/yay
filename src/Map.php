<?php declare(strict_types=1);

namespace Yay;

class Map implements \Countable {
    protected $map = [];

    function get($key) {
        return $this->map[$key] ?? null;
    }

    function add($key, $value = true) {
        return $this->map[$key] = $value;
    }

    function remove($key) {
        unset($this->map[$key]);
    }

    function contains($key) : bool {
        return isset($this->map[$key]);
    }

    function symbols() : array {
        return array_keys($this->map);
    }

    function count() : int {
        return count($this->map);
    }

    static function fromValues(array $values = []) : self {
        $m = self::fromEmpty();
        foreach($values as $value) $m->add($value);

        return $m;
    }

    static function fromKeysAndValues(array $values = []) : self {
        $m = self::fromEmpty();
        foreach($values as $key => $value) $m->add($key, $value);

        return $m;
    }

    static function fromEmpty() : self {
        return new self;
    }
}
