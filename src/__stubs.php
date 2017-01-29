<?php

/*
 * This stub file exists to assist IDEs with their code intelligence parsers.
 */

namespace Yay {

    // Prevent accidental execution
    goto yay_stubs_skip;

    /*
     * Define constants here: https://youtrack.jetbrains.com/issue/WI-7847
     *
     * define() must be used because "const" is compile-time and the current way tests are run,
     * constants need to be loaded multiple times hence the need for conditional constants.
     *
     * This file exists because PHPStorm 10.0.3 does not yet have support for namespaced defines.
     */

    /** @noinspection PhpUnreachableStatementInspection */
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

    yay_stubs_skip:
}