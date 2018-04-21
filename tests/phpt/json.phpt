--TEST--
Proof of concept native json support with PEG macro --pretty-print
--FILE--
<?php

$(macro :grammar) {

    << $(json) { ''json'' '':'' $! $(root) }

    $(root) { $(obj) | $(array_) }

    $(array_) { ''['' $(values) '']'' }

    $(obj) { ''{'' $(pairs) ''}'' }
    $(pairs) { list($(pair) , '','') }
    $(pair) { $(str as key) '':'' $(value) }

    $(values) { list($(value) , '','') }
    $(value) { $(array_) | $(obj) | $(null) | $(false) | $(true) | $(number) | $(str) }

    $(null) { ''null'' }
    $(true) { ''true'' }
    $(false) { ''false'' }
    $(str) { T_CONSTANT_ENCAPSED_STRING }
    $(number) { T_LNUMBER }

} >> {
    JSON_MATCH
}

json : {
    'a' : true,
    'b' : false,
    'c' : null,
    'd' : 'string',
    'e' : {
        'a' : true,
        'b' : false,
        'c' : null,
        'd' : 'string',
        'e' : {
            'f': {}
        },
        'f' : ['', {'g': {'h': {}}}, null, true, false, [], [1]]
    }
};


?>
--EXPECTF--
<?php

JSON_MATCH;

?>
