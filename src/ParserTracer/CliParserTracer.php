<?php declare(strict_types=1);

namespace Yay\ParserTracer;

use Yay\{Parser, Index};

final class CliParserTracer implements ParserTracer
{
    const
        OPTION_TRUNCATE_TRACE = 'truncate',
        OPTION_PRETTY_TRACE = 'numeric_stack_size',
        OPTION_DELAY = 'delay',
        OPTION_COLOR_TRACE = "color.tarce",
        OPTION_COLOR_ATTEMPT = "color.attempt",
        OPTION_COLOR_ERROR = "color.error",
        OPTION_COLOR_PRODUCTION = "color.production"
    ;

    const OPTIONS_DEFAULT = [
        self::OPTION_DELAY => 0,
        self::OPTION_PRETTY_TRACE => true,
        self::OPTION_TRUNCATE_TRACE => 120,
        self::OPTION_COLOR_TRACE => "\033[0;30m",
        self::OPTION_COLOR_ATTEMPT => "\033[0;0m",
        self::OPTION_COLOR_ERROR => "\033[0;31m",
        self::OPTION_COLOR_PRODUCTION => "\033[0;32m",
    ];

    private
        $lastDepth = 0,
        $stack = [],
        $options = []
    ;

    function __construct(array $options = [])
    {
        $this->options =
            $options
            + [self::OPTION_TRUNCATE_TRACE => ((int) exec('tput cols')) ?: self::OPTIONS_DEFAULT[self::OPTION_TRUNCATE_TRACE]]
            + self::OPTIONS_DEFAULT
        ;
    }

    function push(Parser $parser)
    {
        $this->stack[] = $parser;
    }

    function pop(Parser $parser)
    {
        $popped = array_pop($this->stack);

        assert($parser === $popped);
    }

    function trace(Index $index, string $event = 'trace', string $message = '')
    {
        $parser = end($this->stack);

        $output = sprintf(
            "%s%s %s%s at %s from Parser<%s>",
            $this->stackmap(),
            $this->options['color.' . $event] ?? $this->options[self::OPTION_COLOR_TRACE],
            $message  ? $event . ' ' : $event,
            ($message ? "\033[0;7m {$message} " . $this->options['color.' . $event] ?? $this->options[self::OPTION_COLOR_TRACE] : ''),
            $index->token ? $index->token->dump() : 'EOF',
            $parser->__debugInfo()['label'] ?: $this->autolabel($parser)
        );

        if (($offset = mb_strlen($output)) > $this->options[self::OPTION_TRUNCATE_TRACE] + ($message ? 14 : 0)) {
            $output = mb_substr($output, 0, $this->options[self::OPTION_TRUNCATE_TRACE] + ($message ? 14 : 0));
            $output .= '...)>';
        }

        echo $output, "\033[0m", PHP_EOL;
    }

    function autolabel(Parser $parser) : string
    {
        $expected = implode(' ', array_unique(array_map(function($t){ return str_replace('()', '', $t->dump()); }, $parser->expected()->all())));

        return str_replace('Yay\\', '', $parser->__debugInfo()['type']) . '(' . $expected . ')';
    }

    function stackmap() : string
    {
        $depth = count($this->stack);
        $output = str_pad((string) $depth, 3);

        $color = ((int) $depth % 6) + 200;

        if ($this->options[self::OPTION_PRETTY_TRACE]) $output .= str_repeat('│', $depth);

        $this->lastDepth = $depth;

        return $output;
    }
}
