<?php declare(strict_types=1);

namespace Yay;

use
    RuntimeException
;

class Cycle {

    protected
        $id = 0,
        $salt = ''
    ;

    function __construct(string $salt) { $this->salt = $salt; }

    function next() /*: void */ { $this->id++; }

    /**
     * Not security related, just making scope id not humanely predictable.
     */
    function id() : string { return md5($this->salt . $this->id); }
}
