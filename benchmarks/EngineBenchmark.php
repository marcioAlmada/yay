<?php declare(strict_types=1);

use Yay\{Engine, TokenStream};

class EngineBenchmark
{
    private $fixtures;

    function __construct() {
        $this->fixtures = new class {
            function create(string $source) : array {
                $file = sys_get_temp_dir() . '/' . substr(md5($source), 0, 6);
                file_put_contents($file, $source);
                return [
                    'file' =>  $file,
                    'length' => strlen($source),
                ];
            }

            function load(string $file) : string {
                return file_get_contents($file);
            }
        };
    }

    public function sourceProvider()
    {
        yield $this->literalEntryPointMacrosFixture(1000, 2000);
        yield $this->literalEntryPointMacrosFixture(2500, 5000);
        yield $this->literalEntryPointMacrosFixture(5000, 10000);
        yield $this->literalEntryPointMacrosFixture(7500, 15000);
        yield $this->literalEntryPointMacrosFixture(15000, 30000);
        yield $this->nonLiteralEntryPointMacrosFixture(5000);
        yield $this->nonLiteralEntryPointMacrosFixture(10000);
    }

    /**
     * @OutputTimeUnit("milliseconds")
     * @Warmup(2)
     * @Iterations(5)
     * @ParamProviders({"sourceProvider"})
     */
    public function benchMacroExpansion(array $params)
    {
        $expansion = (new Engine)->expand($this->fixtures->load($params['file']), 'bench.php');
    }

    private function literalEntryPointMacrosFixture(int $min, int $max) : array {
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

        return $this->fixtures->create($source);
    }

    private function nonLiteralEntryPointMacrosFixture(int $max) : array {
        $source = <<<SRC
<?php
//
$(macro) { $(T_STRING as s) } >> { __$(s) } // slow macro
SRC;
        
        $fixture = str_replace(
            '<?php declare(strict_types=1);', '',
            file_get_contents(__DIR__ . '/../src/parsers.php')
        );

        while(substr_count($source, PHP_EOL) < $max) $source .= $fixture;

        return $this->fixtures->create($source);
    }
}

