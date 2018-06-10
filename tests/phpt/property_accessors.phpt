--TEST--
General property  --pretty-print
--FILE--
<?php

$(macro) {
    $(ns() as class) $({...} as body)
} >> {
    $(class) {
        use \Pre\AccessorsTrait;

        $(body)
    }
}

$(macro) {
    private $(T_VARIABLE as variable) {
        $(
            repeat
            (
                either
                (
                    chain
                    (
                        get,
                        optional(chain(token(':'), ns())) as getter_return_type,
                        between(token('{'), layer(), token('}')) as getter_body
                    )
                    as getter
                    ,
                    chain
                    (
                        set,
                        token('('),
                        layer() as setter_args,
                        token(')'),
                        optional(chain(token(':'), ns())) as setter_return_type,
                        between(token('{'), layer(), token('}')) as setter_body
                    )
                    as setter
                    ,
                    chain
                    (
                        unset,
                        optional(chain(token(':'), ns())) as unsetter_return_type,
                        between(token('{'), layer(), token('}')) as unsetter_body
                    )
                    as unsetter
                )
            ) as accessors
        )
    };
} >> {
    private $(variable);

    $(accessors ... {
        $(setter ?... {
            private function $$(concat(__set_ $$(unvar($(variable)))))($(setter_args)) $(setter_return_type) {
                $(setter_body)
            }

        })

        $(getter ?... {
            private function $$(concat(__get_ $$(unvar($(variable)))))() $(getter_return_type) {
                $(getter_body)
            }
        })

        $(unsetter ?... {
            private function $$(concat(__unset_ $$(unvar($(variable)))))() $(unsetter_return_type) {
                $(unsetter_body)
            }
        })
    })
}

namespace App;

class Sprocket
{
    private $type {
        set(string $value) {
            $this->type = $value;
        }

        get :string {
            return $this->type;
        }

        unset {
            $this->type = '';
        }
    };

    private $name {
        get :string {
            return $this->name;
        }
    };
}

?>
--EXPECTF--
<?php

namespace App;

class Sprocket
{
    use \Pre\AccessorsTrait;
    private $type;
    private function __set_type(string $value)
    {
        $this->type = $value;
    }
    private function __get_type() : string
    {
        return $this->type;
    }
    private function __unset_type()
    {
        $this->type = '';
    }
    private $name;
    private function __get_name() : string
    {
        return $this->name;
    }
}

?>
