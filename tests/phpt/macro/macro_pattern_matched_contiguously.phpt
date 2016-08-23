--TEST--
Macro pattern matched contiguously --pretty-print
--FILE--
<?php

declare(strict_types=1);

macro {
    T_STRING·method (···args) { ···body }
} >> {
    function T_STRING·method (···args) {
        ···body
    }
}

/**
 * @group small
 */
class FooTest
{
    barProvider()
    {
        return [['a', 'b', true], ['a', 'b', false]];
    }
    testBar($a, $b, bool $assertion)
    {
        'method body';
    }
    testFoo()
    {
        'method body';
    }
    testBaz()
    {
        'method body';
    }
}
?>
--EXPECTF--
<?php

declare (strict_types=1);
/**
 * @group small
 */
class FooTest
{
    function barProvider()
    {
        return [['a', 'b', true], ['a', 'b', false]];
    }
    function testBar($a, $b, bool $assertion)
    {
        'method body';
    }
    function testFoo()
    {
        'method body';
    }
    function testBaz()
    {
        'method body';
    }
}

?>
