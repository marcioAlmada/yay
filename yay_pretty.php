<?php declare(strict_types=1);

use PhpParser\{ Error, ParserFactory, PrettyPrinter };

function yay_pretty(string $source) : string {
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
    $prettyPrinter = new PrettyPrinter\Standard;
    $stmts = $parser->parse($source);

    return $prettyPrinter->prettyPrintFile($stmts);
}
