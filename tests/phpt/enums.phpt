--TEST--
Proof of concept backport of enums from future --pretty-print
--FILE--
<?php

// things here would normally be under a namespace, but since we want a concise example...

interface Enum
{
}

function enum_field_or_class_constant(string $class, string $field)
{
    return (\in_array(\Enum::class, \class_implements($class)) ? $class::$field() : \constant("{$class}::{$field}"));
}

$(macro :unsafe) {
    // the enum declaration
    enum $(T_STRING as name) {
        $(
            ls
            (
                label() as field
                ,
                token(',')
            )
            as fields
        )
    }
} >> {
    class $(name) implements Enum {
        private static $store;

        private function __construct() {}

        static function __callStatic(string $field, array $args) : self {
            if(! self::$store) {
                self::$store = new \stdclass;
                $(fields ... {
                    self::$store->$(field) = new class extends $(name) {};
                })
            }

            if (isset(self::$store->$field)) return self::$store->$field;

            throw new \Exception('Undefined enum field ' . __CLASS__ . "->{$field}.");
        }
    }
}

$(macro) {
    $(
        // sequence that matches the enum field access syntax:
        chain(
            ns() as class, // matches a namespace
            token(T_DOUBLE_COLON), // matches T_DOUBLE_COLON used for static access
            not(class), // avoids matching ::class resolution syntax
            label() as field, // matches the enum field name
            not(token('(')) // avoids matching static method calls
        )
    )
} >> {
    \enum_field_or_class_constant($(class)::class, $$(stringify($(field))))
}

//

enum Fruits { Apple, Orange }

// macro should work with Enums only

var_dump(Fruits::Orange instanceof Fruits);
var_dump(Fruits::Orange <=> Fruits::Apple);
var_dump(Fruits::Apple);

// macro skips class constants access

class NotEnum {
    const Orange = 1;
    static function method() {}
}

var_dump(NotEnum::Orange);

// macro skips ::class resolution

var_dump(NotEnum::class);

// macro skips static method calls

var_dump(NotEnum::method());

?>
--EXPECTF--
<?php

// things here would normally be under a namespace, but since we want a concise example...
interface Enum
{
}
function enum_field_or_class_constant(string $class, string $field)
{
    return \in_array(\Enum::class, \class_implements($class)) ? $class::$field() : \constant("{$class}::{$field}");
}
//
class Fruits implements Enum
{
    private static $store;
    private function __construct()
    {
    }
    static function __callStatic(string $field, array $args) : self
    {
        if (!self::$store) {
            self::$store = new \stdclass();
            self::$store->Apple = new class extends Fruits
            {
            };
            self::$store->Orange = new class extends Fruits
            {
            };
        }
        if (isset(self::$store->{$field})) {
            return self::$store->{$field};
        }
        throw new \Exception('Undefined enum field ' . __CLASS__ . "->{$field}.");
    }
}
// macro should work with Enums only
var_dump(\enum_field_or_class_constant(Fruits::class, 'Orange') instanceof Fruits);
var_dump(\enum_field_or_class_constant(Fruits::class, 'Orange') <=> \enum_field_or_class_constant(Fruits::class, 'Apple'));
var_dump(\enum_field_or_class_constant(Fruits::class, 'Apple'));
// macro skips class constants access
class NotEnum
{
    const Orange = 1;
    static function method()
    {
    }
}
var_dump(\enum_field_or_class_constant(NotEnum::class, 'Orange'));
// macro skips ::class resolution
var_dump(NotEnum::class);
// macro skips static method calls
var_dump(NotEnum::method());

?>
