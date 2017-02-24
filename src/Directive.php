<?php declare(strict_types=1);

namespace Yay;

interface Directive {

    function id() : int;

    function tags() : Map;

    function pattern() : Pattern;

    function expansion() : Expansion;

    function apply(TokenStream $TokenStream, Engine $engine);
}
