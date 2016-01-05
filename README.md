# YAY!

**YAY!** is a high level parser combinator based PHP preprocessor that allows anyone to augment PHP with PHP :boom:

This means that language features could be distributed as composer packages (as long as the macro based implementations
can be expressed in pure PHP code, and the implementation is fast enough).

> Not ready for real world usage yet :bomb:

[Roadmap](https://github.com/marcioAlmada/yay/issues/3).

## How it works

### Very Simple Example

Every macro consist of a matcher and an expander that when executed allows you to augment PHP.
Consider the simplest example possible:

```php
macro { $ } >> { $this } // this shorthand
```

The macro is basically expanding a literal `$` token to `$this`. The following code would expand to:

```php
// source                                |   // expansion
class Foo {                              |   class Foo {
    protected $a = 1, $b = 2, $c = 3;    |     protected $a = 1, $b = 2, $c = 3;
                                         |        
    function getProduct(): int {         |     function getProduct(): int {
      return $->a * $->b * $->c;         |       return $this->a * $this->b *$this->c;
    }                                    |     }
}                                        |   }
```

### Simple Example

Apart from literal characher sequences, it's also possible to match specific token types using the token matcher in
the form of `TOKEN_TYPE·label`.

The following macro matches token sequences like `swap!($x, $y)` or `swap!($foo, $bar)`:

```php
macro {
    swap ! ( T_VARIABLE·A , T_VARIABLE·B ) // swap values between two variables
} >> {
    (list(T_VARIABLE·A, T_VARIABLE·B) = [T_VARIABLE·B, T_VARIABLE·A])
}
```

The expansion should be pretty obvious:
```php
// source             |    // expansion
swap!($foo, $bar);    |    (list($foo, $bar) = [$bar, $foo]); 
```

### Advanced Example

A more complex example could be porting enums from the future to PHP with a syntax like:

```php
enum Fruits {
    Apple,
    Orange
}

var_dump(\Fruits->Orange <=> \Fruits->Apple);
```
So, syntactically, enums are declared with the literal `enum` word followed by a `T_STRING` and a comma
separated list of identifiers withing braces `{A, B, C}`.

YAY uses parser combinators internally for everything and these more high level parsers are fully
exposed on macro declarations. Our enum macro will need high level matchers like `·ls()` and `·rtoken()`
combined to match the desired syntax, like so:

```php
macro {
    enum T_STRING·name {
        ·ls
        (
            ·word()·field
            ,
            ','
        )
        ·fields
    }
} >> {
    "it works";
}
```

The macro is already capable to match the enums:

```php
// source                      // expansion
enum Order {ASC, DESC};    |   "it works";
```

I won't explain how enums are implemented, you can read the [RFC](https://wiki.php.net/rfc/enum) if you wish
and then see how the expansion below works:

```php
macro {
    enum T_STRING·name {
        ·ls
        (
            ·word()·field
            ,
            ','
        )
        ·fields
    }
} >> {
    class T_STRING·name {
        private static $store;

        private function __construct(){}

        static function __(string $field) : self {
            if(! self::$store) {
                self::$store = new \stdclass;
                ·fields ··· {
                    self::$store->·field = new class extends T_STRING·name {};
                }
            }

            if (! $field = self::$store->$field ?? false)
                throw new \Exception(
                    "Undefined enum field " . __CLASS__ . "->{$field}.");

            return $field;
        }
    }
}

macro { \·ns()·enum_name->T_STRING·field } >> { \·enum_name::__(·stringify(T_STRING·field)) }
```

> More examples within the phppt tests folder https://github.com/marcioAlmada/yay/tree/master/tests/phppt

# Conclusion

For now this is an experiment about how to build a high level preprocessor DSL using parser combinators
on a languages like PHP. Why?

PHP is very far from being [homoiconic](https://en.wikipedia.org/wiki/Homoiconicity) and therefore requires
complex deterministic parsing and a big AST implementation with a node visitor API to modify source code - and
in the end, you're not even able to easily process unknown syntax `¯\_(⊙_ʖ⊙)_/¯`.

That's why this project was born. It was also part of the challenge:

0. Create a minimalistic architecture that exposes a subset of the internal components, that power the preprocessor itself, to the user DSL.
0. Create parser combinators with decent error reporting and grammar invalidation, because of 1

## Copyright

Copyright (c) 2015-* Márcio Almada. Distributed under the terms of an MIT-style license.
See LICENSE for details.
