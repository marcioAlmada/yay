--TEST--
Proof of concept inlined generics
--FILE--
<?php

macro ·unsafe {

    /** generic stack class macro */
    Stack < ·ns()·type >

} >> {

    class {
        private $stack = [];

        function push(·type $item) {
            $this->stack[] = $item;
        }

        function pop() : ·type {
            return end($this->stack);
        }
    }
}

new Stack
    <
    stdclass
>;

$stack = new Stack<stdclass>;

$stack->push(new stdclass);
$stack->push(new ArrayObject);

$stack = new Stack<\Some\Full\Qualified\ClassName>;

?>
--EXPECTF--
<?php

new class {
        private $stack = [];

        function push(stdclass $item) {
            $this->stack[] = $item;
        }

        function pop() : stdclass {
            return end($this->stack);
        }
    };

$stack = new class {
        private $stack = [];

        function push(stdclass $item) {
            $this->stack[] = $item;
        }

        function pop() : stdclass {
            return end($this->stack);
        }
    };

$stack->push(new stdclass);
$stack->push(new ArrayObject);

$stack = new class {
        private $stack = [];

        function push(\Some\Full\Qualified\ClassName $item) {
            $this->stack[] = $item;
        }

        function pop() : \Some\Full\Qualified\ClassName {
            return end($this->stack);
        }
    };

?>
