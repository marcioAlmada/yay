--TEST--
Proof of concept native json support with PEG macro --pretty-print
--FILE--
<?php

macro ·grammar {

    << ·json { ''json'' '':'' !! ·root }

    ·root { ·object | ·array }

    ·array { ''['' ·values '']'' }

    ·object { ''{'' ·pairs ''}'' }
    ·pairs { list(·pair , '','') }
    ·pair { ·string{}·key '':'' ·value }

    ·values { list(·value , '','') }
    ·value { ·array | ·object | ·null | ·false | ·true | ·number | ·string }

    ·null { ''null'' }
    ·true { ''true'' }
    ·false { ''false'' }
    ·string { T_CONSTANT_ENCAPSED_STRING }
    ·number { T_LNUMBER }

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
