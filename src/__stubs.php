<?php

namespace Yay {
    /*
     * Define constants here: https://youtrack.jetbrains.com/issue/WI-7847
     *
     * define() must be used because "const" is compile-time and the current way tests are run,
     * constants need to be loaded multiple times hence the need for conditional constants.
     *
     * This file exists because PHPStorm 10.0.3 does not yet have support for namespaced defines.
     */

    const LAYER_DELIMITERS = [
        '{' => 1,
        T_CURLY_OPEN => 1,
        T_DOLLAR_OPEN_CURLY_BRACES => 1,
        '}' => -1,
        '[' => 1,
        ']' => -1,
        '(' => 1,
        ')' => -1,
    ];

    const CONSUME_DO_TRIM = 0x10;
    const CONSUME_NO_TRIM = 0x01;
}