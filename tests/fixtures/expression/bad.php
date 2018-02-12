<?php

return [
    __LINE__ => '++',
    __LINE__ => '&$foo->bar["baz"]',
    __LINE__ => '&$var',
    __LINE__ => '1 1 1',
    __LINE__ => '1 -- 1',
    __LINE__ => '(',
    __LINE__ => '() ',
    __LINE__ => '(() ',
    __LINE__ => '))',
    __LINE__ => ')',
    __LINE__ => '(((([))))',
    __LINE__ => '1 + 1 + (2 + )',
    __LINE__ => '1 + 1 + (2 + (3 + 4)',
    __LINE__ => '(function',
    __LINE__ => '${var}', // because var is reserved, because PHP
    __LINE__ => '${const}', // because const is reserved, because PHP
    __LINE__ => '($foo->bar(->baz)',
    __LINE__ => '($foo)->bar(->baz)',
    __LINE__ => '($foo->bar()->baz',
    __LINE__ => '($foo->bar) = ',
    __LINE__ => '@->baz',
    __LINE__ => '"foo $foo->bar->baz->bar $foo->bar(1, 2, 3)->baz->bar()->biz $foo',
    __LINE__ => '"foo ${bar {$baz} $boo {$foo->bar->baz} {$foo->bar->baz()} {$foo->bar()->baz()}"',
    __LINE__ => '"foo ${bar $baz} $boo {$foo->bar->baz} {$foo->bar->baz()} {$foo->bar()->baz()}"',
    __LINE__ => '[1,, 2,, (3)]',
    __LINE__ => '[1, 2, (3)],',
    __LINE__ => '[1, 2, 3,,] + [1, 2, 3, 4, 5,]',
    __LINE__ => '[1, 2, 3,] + [1,, 2, 3, 4, 5,]',
    __LINE__ => '$foo->bar()->(baz)',
];
