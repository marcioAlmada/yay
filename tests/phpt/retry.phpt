--TEST--
Proof of concept "retry" implementation --pretty-print
--FILE--
<?php

$(macro) { retry; } >> { goto retry; }

$(macro) {
    try {
        $(layer() as try_body)
    }
    catch($(ns() as type) $(T_VARIABLE as exception)) {
        $(layer() as catch_body)
    }
} >> {
    /*
        This implementation is full of issues:

        - no recursion support (try catch inside another try catch)
        - macro hygienization can fail under weird circuntances
        - a more procedural macro api would be necessary
    */
    try {
        retry:
        $(try_body)
    }
    catch($(type) $(exception)) {
        $$(expand($(catch_body)))
    }
}

function request_something() {
    static $count = 0;

    if ($count < 3) {
        $count++;
        throw new \Exception("Tried {$count}", 1);
    }
}

try {
    request_something();
}
catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    retry;
}

echo 'END';
?>
--EXPECTF--
<?php

function request_something()
{
    static $count = 0;
    if ($count < 3) {
        $count++;
        throw new \Exception("Tried {$count}", 1);
    }
}
try {
    retry___0:
    request_something();
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    goto retry___0;
}
echo 'END';

?>
