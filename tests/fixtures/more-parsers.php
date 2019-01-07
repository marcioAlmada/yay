<?php

namespace Custom {
    use Yay\Parser;
    use function Yay\buffer;

    function parser() : Parser {
        return buffer("found")->as("parser");
    }
}
