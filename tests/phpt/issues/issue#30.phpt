--TEST--
Issue #30 --pretty-print
--FILE--
<?php

macro ·global ·unsafe {
    ·lst
    (
        ·chain
        (
            ·token('@'), ·ns()·class,
            ·token('('),
            ·optional
            (
                ·lst
                (
                    ·chain(T_STRING·field, ·token('='), ·label()·value),
                    ·token(',')
                )
                ·annotation_arguments
            ),
            ·token(')')
        ),
        ·token(';')
    )
    ·annotations

    class T_STRING·class_name
} >> {
    ·annotations ··· {
        new class(new \ReflectionClass(T_STRING·class_name::class)) extends ·class
        {
            public function __construct(\ReflectionClass $context)
            {
                $fields = [];
                ·annotation_arguments ··· {
                    $this->T_STRING·field = $fields[··stringify(T_STRING·field)] = ·value;
                }
                parent::__construct($fields, $context);
            }
        };
    }
    class T_STRING·class_name
}

@EmptyAparameters();
@Any(foo = 1);
@Some(foo = 2, bar = 3);
@ParametersWithTrailingDelimiter(foo = 4, bar = 5,);
class Test
{
}

?>
--EXPECTF--
<?php

new class(new \ReflectionClass(Test::class)) extends EmptyAparameters
{
    public function __construct(\ReflectionClass $context)
    {
        $fields = [];
        parent::__construct($fields, $context);
    }
};
new class(new \ReflectionClass(Test::class)) extends Any
{
    public function __construct(\ReflectionClass $context)
    {
        $fields = [];
        $this->foo = $fields['foo'] = 1;
        parent::__construct($fields, $context);
    }
};
new class(new \ReflectionClass(Test::class)) extends Some
{
    public function __construct(\ReflectionClass $context)
    {
        $fields = [];
        $this->foo = $fields['foo'] = 2;
        $this->bar = $fields['bar'] = 3;
        parent::__construct($fields, $context);
    }
};
new class(new \ReflectionClass(Test::class)) extends ParametersWithTrailingDelimiter
{
    public function __construct(\ReflectionClass $context)
    {
        $fields = [];
        $this->foo = $fields['foo'] = 4;
        $this->bar = $fields['bar'] = 5;
        parent::__construct($fields, $context);
    }
};
class Test
{
}

?>
