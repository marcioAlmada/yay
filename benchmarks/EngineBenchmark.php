<?php

use Yay\{Engine, TokenStream};

class EngineBenchmark
{
    public function sourceProvider()
    {
        yield ['source' => $this->literalEntryPointMacrosFixture(1000, 2000)];
        yield ['source' => $this->literalEntryPointMacrosFixture(2500, 5000)];
        yield ['source' => $this->literalEntryPointMacrosFixture(5000, 10000)];
        yield ['source' => $this->literalEntryPointMacrosFixture(7500, 15000)];
        yield ['source' => $this->literalEntryPointMacrosFixture(15000, 30000)];
        yield ['source' => $this->nonLiteralEntryPointMacrosFixture(5000)];
        yield ['source' => $this->nonLiteralEntryPointMacrosFixture(10000)];
    }

    /**
     * @OutputTimeUnit("milliseconds")
     * @Warmup(2)
     * @Iterations(5)
     * @ParamProviders({"sourceProvider"})
     */
    public function benchMacroExpansion(array $params)
    {
        $expansion = (new Engine)->expand($params['source'], 'bench.php');
    }

    private function literalEntryPointMacrosFixture(int $min, int $max) : string {
        $source = <<<SRC
<?php
//
$(macro) { function } >> { __function }
$(macro) { extends } >> { __extends }
$(macro) { Parser } >> { __Parser }
$(macro) { new } >> { __new }
$(macro) { instanceof $(T_STRING as s) } >> { __instanceof $(s) }
SRC;
        
        $fixture = str_replace(
            '<?php declare(strict_types=1);', '',
            file_get_contents(__DIR__ . '/../src/parsers.php')
        );

        while(substr_count($source, PHP_EOL) < $min) $source .= $fixture;

        $source .= <<<SRC
        $(macro) { new } >> { __new } // redundant macro
SRC;

        while(substr_count($source, PHP_EOL) < $max) $source .= $fixture;

        return $source;
    }

    private function nonLiteralEntryPointMacrosFixture(int $max) : string {
        $source = <<<SRC
<?php
//
$(macro) { $(T_STRING as s) } >> { $(s) } // slow macro
SRC;
        
        $fixture = str_replace(
            '<?php declare(strict_types=1);', '',
            file_get_contents(__DIR__ . '/../src/parsers.php')
        );

        while(substr_count($source, PHP_EOL) < $max) $source .= $fixture;

        return $source;
    }
}