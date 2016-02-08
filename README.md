# YAY!

[![Join the chat at https://gitter.im/marcioAlmada/yay](https://badges.gitter.im/marcioAlmada/yay.svg)](https://gitter.im/marcioAlmada/yay?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

**YAY!** is a high level parser combinator based PHP preprocessor that allows anyone to augment PHP with PHP :boom:

This means that language features could be distributed as composer packages (as long as the macro based implementations
can be expressed in pure PHP code, and the implementation is fast enough).

> Not ready for real world usage yet :bomb:

[Roadmap](https://github.com/marcioAlmada/yay/issues/3).

## Set Up

```bash
composer require yay/yay:dev-master
```

## Usage

### Command Line

```
yay some/file/with/macros.php >> target/file.php
```

### Runtime Mode

The "runtime" mode is W.I.P and will use stream wrappers along with composer integration in order
to preprocess every file that gets included. It may have some opcache/cache support, so files will be
only preprocessed/expanded once and when needed.

See feature progress at issue [#11](https://github.com/marcioAlmada/yay/issues/11).

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
    protected $a = 1, $b = 2, $c = 3;    |       protected $a = 1, $b = 2, $c = 3;
                                         |        
    function getProduct(): int {         |       function getProduct(): int {
        return $->a * $->b * $->c;       |           return $this->a * $this->b *$this->c;
    }                                    |       }
}                                        |   }
```

### Simple Example

Apart from literal characher sequences, it's also possible to match specific token types using the token matcher in
the form of `TOKEN_TYPE·label`.

The following macro matches token sequences like `__swap($x, $y)` or `__swap($foo, $bar)`:

```php
macro {
    __swap ( T_VARIABLE·A , T_VARIABLE·B ) // swap values between two variables
} >> {
    (list(T_VARIABLE·A, T_VARIABLE·B) = [T_VARIABLE·B, T_VARIABLE·A])
}
```

The expansion should be pretty obvious:
```php
// source              |    // expansion
__swap($foo, $bar);    |    (list($foo, $bar) = [$bar, $foo]); 
```

### Another Simple Example

To implement `unless` we need to match the literal `unless` keyword followed by a layer of tokens between parentheses
`(...)` and a block of code `{...}`. Fortunately, the macro DSL has a very straightforward layer matching construct:

```php
macro {
    unless (···expression) { ···body }
} >> {
    if (! (···expression)) {
        ···body
    }
}
```

The macro in action:

```php
// source                   |   // expansion
unless ($x === 1) {         |   if (! ($x === 1)) {
    echo "\$x is not 1";    |       echo "\$x is not 1";
}                           |   }
```

> PS: Please don't implement "unless". This is here just for didactic reasons.

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

> More examples within the phpt tests folder https://github.com/marcioAlmada/yay/tree/master/tests/phpt

# FAQ

> Why "YAY!"?

\- PHP with feature "x": yay or nay? :wink:

> Where is the documentation?

Sorry, there is no documentation yet...

> Why did you use a middle dot `·` charachter?

This is still just an experiment but you can find some research done on issue [#1](https://github.com/marcioAlmada/yay/issues/1). I'm open to suggestions to have a more ergonomic macro DSL :)

> Why TF are you working on this?

Because it's being fun. It may become useful. [Because we can™](https://github.com/haskellcamargo/because-we-can).

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
