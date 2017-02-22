<?php declare(strict_types=1);

namespace Yay;

interface PatternInterface {

    function match(TokenStream $ts);

    function specificity() : int;

    function expected() : Expected;
}
