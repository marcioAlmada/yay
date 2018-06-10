<?php namespace Yay\Fixtures\Wrapper;

$(macro) {
    type $(T_STRING as newtype) = $(T_STRING as basetype);
} >> {
    \\$(macro) {
        \\$(either(instanceof, token(','), token('('), token(':')) as prec) $(newtype)
    } >> {
        \\$(prec) $(basetype)
    }
}

type Path = string;

function test_type_alias(Path $p) : Path {
    return 'pass';
}
