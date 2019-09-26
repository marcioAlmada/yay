<?php declare(strict_types=1);

namespace Yay;

/**
 * @group large
 */
class MacroScopeTest extends \PHPUnit\Framework\TestCase {

    const
        ABSOLUTE_FIXTURES_DIR = __DIR__ . '/fixtures/scope'
    ;

    function testGlobalMacro() {
        $engine = new Engine;

        $source = str_replace('<?php', '', $engine->expand(file_get_contents(self::ABSOLUTE_FIXTURES_DIR . '/macros.php')));
        $this->assertSame([true, true], eval($source));

        $source = str_replace('<?php', '', $engine->expand(file_get_contents(self::ABSOLUTE_FIXTURES_DIR . '/run.php')));
        $this->assertSame([true, 'LOCAL_MACRO'], eval($source));
    }
}
