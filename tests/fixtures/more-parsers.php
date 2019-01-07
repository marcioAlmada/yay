<?php

namespace Custom {
    use Yay\Parser;
    use function Yay\buffer;
    use function Yay\chain;

    function helloWorld() : Parser {
        return chain(
            buffer("hello")->as("first"),
            buffer("world")->as("second")
        )->as("alias");
    }
}
