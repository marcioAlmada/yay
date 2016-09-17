--TEST--
Defer
--FILE--
<?php

macro {
    defer ·closure()·deferred;
} >> {
    $deferred = new class(·deferred) {
        ··unsafe {
        private $deferred = null;
        function __construct(callable $deferred){ $this->deferred = $deferred; }
        function __destruct(){ ($this->deferred)(); }
        }
    };
}

function app($input){
    defer function(){ echo 'Bye!', PHP_EOL; };
    defer function() use ($input) { echo "Handling {$input}\n"; };
    echo 'Hello world!', PHP_EOL;
}

app("request");

?>
--EXPECTF--
<?php

function app($input){
    $deferred·0 = new class(function(){echo 'Bye!', PHP_EOL; }) {
        private $deferred = null;
        function __construct(callable $deferred){ $this->deferred = $deferred; }
        function __destruct(){ ($this->deferred)(); }
        
    };
    $deferred·1 = new class(function()use($input){echo "Handling {$input}\n"; }) {
        private $deferred = null;
        function __construct(callable $deferred){ $this->deferred = $deferred; }
        function __destruct(){ ($this->deferred)(); }
        
    };
    echo 'Hello world!', PHP_EOL;
}

app("request");

?>
