<?php declare(strict_types=1);

namespace Yay;

interface Context {
    function symbols() : array;
    function get($key);
}
