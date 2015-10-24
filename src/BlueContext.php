<?php declare(strict_types=1);

namespace Yay;

class BlueContext {

    protected
        /**
         * Stores a disabling context as [int $id => true]
         */
        $context = []
    ;

    function contains(int $id) : bool {
        return isset($this->context[$id]);
    }

    function add(int $id) {
        $this->context[$id] = true;
    }

    function inherit(self $subject) {
        foreach (array_keys($subject->context) as $id) $this->add($id);
    }
}
