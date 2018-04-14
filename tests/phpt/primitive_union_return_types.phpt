--TEST--
Primitive union return types --pretty-print
--FILE--
<?php

$(macro :unsafe) {
    // this doesn't work with scalars yet
    // a more procedural macro construct would be necessary
    function $(optional(token(T_STRING) as name)) $((...) as args)
    // match union return type, like ":<A>|<\B>|<A\B\C>"
    $(
        optional
        (
            chain
            (
                token(':'),
                ls
                (
                    ns() as type,
                    token('|')
                )
                as union
            )
            as return_type
        )
    )
    $({...} as body)
} >> {
    function $(name) ($(args))
    {
        $fn = (function($(args)){
            $(body)
        });

        $ret = isset($this)
            ? $fn->call($this, ...function_get_args())
            : $fn(...function_get_args());

        if (
            $(return_type ... {
                $(union ... ( && ) {
                    ! $ret instanceof $(type)
                })
            })
        ) {
            throw new TypeError("Some fancy type Error");
        }

        return $ret;
    }
}

class Foo {
    function bar(bool $x) : A|Foo\B|\Foo\Bar\C {
        if ($x) {
            return new Z;
        } else {
            return new A;
        }
    }
}

$fn = function() : Foo|Bar {
    return null;
};

?>
--EXPECTF--
<?php

class Foo
{
    function bar(bool $x)
    {
        $fn = function (bool $x) {
            if ($x) {
                return new Z();
            } else {
                return new A();
            }
        };
        $ret = isset($this) ? $fn->call($this, ...function_get_args()) : $fn(...function_get_args());
        if (!$ret instanceof A && !$ret instanceof Foo\B && !$ret instanceof \Foo\Bar\C) {
            throw new TypeError("Some fancy type Error");
        }
        return $ret;
    }
}
$fn = function () {
    $fn = function () {
        return null;
    };
    $ret = isset($this) ? $fn->call($this, ...function_get_args()) : $fn(...function_get_args());
    if (!$ret instanceof Foo && !$ret instanceof Bar) {
        throw new TypeError("Some fancy type Error");
    }
    return $ret;
};

?>
