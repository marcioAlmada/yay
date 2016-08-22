<?php declare(strict_types=1);

namespace Yay;

use
    Exception,
    RecursiveDirectoryIterator,
    RecursiveIteratorIterator,
    RegexIterator,
    PHPUnit_Framework_Assert as Assert
;

/**
 * @group large
 */
class SpecsTest extends \PHPUnit_Framework_TestCase
{
    public static function setupBeforeClass() {
        /**
         * Abusing namespaces to make Cycle->id() predictable during tests only!
         */
        function md5($foo) { return $foo; }
    }

    function specProvider() : array {
        $files = new RegexIterator(
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(__DIR__ . '/phpt/')
            )
            ,
            '/\.phpt$/', RegexIterator::MATCH
        );

        $tests = [];

        foreach ($files as $i => $test)
            $tests[$i] = [new Test($test->getRealPath())];

        return $tests;
    }

    /**
     * @dataProvider specProvider
     */
    function testSpecs(Test $test) {
        try {
            $test->run();
            $test->clean();
        } catch(\Exception $e) {
            $test->dump();

            throw $e;
        }

        $this->assertNotEquals(Test::BORKED, $test->status(), 'Test borked!');
    }
}

class Test {

    const
        BORKED = 'BORKED',
        FAILED = 'FAILED',
        PASSED = 'PASSED',
        DIFFCM = '\
            diff --strip-trailing-cr \
            --label "%s" \
            --label "%s" \
            --unified "%s" "%s"'
        ;


    protected
        $status,
        $name,
        $source,
        $expected,
        $out,
        $file,
        $file_expect,
        $file_diff,
        $file_out
        ;

    function __construct(string $file) {
        $this->file = $file;
        $this->file_expect = preg_replace('/\.phpt$/', '.exp', $this->file);
        $this->file_diff = preg_replace('/\.phpt$/', '.diff', $this->file);
        $this->file_out = preg_replace('/\.phpt$/', '.out', $this->file);
        // $this->file_php = preg_replace('/\.phpt$/', '.php', $this->file);
    }

    function run() {
        $raw = is_readable($this->file) ? file_get_contents($this->file) : '';
        $sections = array_values(
                        array_filter(
                            array_map(
                                'trim', preg_split('/^--(TEST|FILE|EXPECTF)--$/m', $raw))));

        if(3 === count($sections)) {
            list($this->name, $this->source, $this->expected) = $sections;

            try {
                $this->out = yay_parse($this->source);
                if (false !== strpos($this->name, '--pretty-print'))
                    $this->out = yay_pretty($this->out) . PHP_EOL . PHP_EOL . '?>';
            } catch(YayParseError $e) {
                $this->out = $e->getMessage();
                // $this->out = (string) $e;
            } catch(\PhpParser\Error $e){
                $this->out = 'PHP ' . $e->getMessage();
                // $this->out = (string) $e;
            } catch(Exception $e) {
                $this->out = (string) $e;
            }

            try{
                Assert::assertStringMatchesFormat($this->expected, $this->out);
                $this->status = self::PASSED;
            }
            catch(Exception $e) {
                $this->status = self::FAILED;

                throw $e;
            }
        }
        else
            $this->status = self::BORKED;
    }

    function status() : string {
        return $this->status;
    }

    function diff() : string {
        $diff = '';
        if($this->status === self::FAILED) {
            exec(
                sprintf(
                    self::DIFFCM, "expect", "out", $this->file_expect, $this->file_out), $out);
            $diff = implode(PHP_EOL, (array) $out);
        }

        return $diff;
    }

    function dump() {
        @file_put_contents($this->file_expect, $this->expected . PHP_EOL);
        @file_put_contents($this->file_out, $this->out . PHP_EOL);
        @file_put_contents($this->file_diff, $this->diff());
        // @file_put_contents($this->file_php, $this->source);
    }

    function clean() {
        @unlink($this->file_expect);
        @unlink($this->file_out);
        @unlink($this->file_diff);
        // @unlink($this->file_php);
    }

    function __toString() {
        $relative_file = str_replace(__DIR__ . '/', '', $this->file);

        return "[{$this->status}] {$this->name} [{$relative_file}]";
    }
}
