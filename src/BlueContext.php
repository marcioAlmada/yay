<?php declare(strict_types=1);

namespace Yay;

class BlueContext extends Map {
    function add($value) {
        return $this->map[$value] = true;
    }

    function contains($value) : bool {
        return isset($this->map[$value]);
    }

    function inherit(self $subject) {
        $this->map += $subject->map;
    }
}
