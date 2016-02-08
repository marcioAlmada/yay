--TEST--
Guards
--FILE--
<?php

class GuardError extends \Error {}

macro { guard (···condition) : ·string()·message ; } >> {
    guard (···condition) {
        throw new \GuardError(·message);
    }
}

macro { guard (···condition) {···body} } >> {
    if (! (···condition)) {
        ···body
        throw new \GuardError("Guard error.");
    }
}

///

function repeat(int $times, callable $action) {
    guard ($times > 1) : '$times must be larger than 1';

    guard ($callable instanceof Action::class) {
        throw new \InvalidArgumentException('$callable must be instance of Action.');
    }
}

?>
--EXPECTF--
<?php

class GuardError extends \Error {}

///

function repeat(int $times, callable $action) {
    if (! ($times > 1)) {
        throw new \GuardError('$times must be larger than 1');
    
        throw new \GuardError("Guard error.");
    }

    if (! ($callable instanceof Action::class)) {
        throw new \InvalidArgumentException('$callable must be instance of Action.');
    
        throw new \GuardError("Guard error.");
    }
}

?>
