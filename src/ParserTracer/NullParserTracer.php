<?php declare(strict_types=1);

namespace Yay\ParserTracer;

use Yay\{Parser, Index};

final class NullParserTracer implements ParserTracer
{
    function push(Parser $parser){}
    function pop(Parser $parser){}
    function trace(Index $index, string $event = '', string $message = ''){}
}
