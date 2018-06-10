--TEST--
Guards
--FILE--
<?php

class FlowError extends \Error {}

$(macro) { UNREACHABLE() } >> {
    throw new \FlowError('Unreachable point reached.')
}

$(macro) { UNREACHABLE ($(string() as message)) } >> {
    throw new \FlowError($(message))
}

$var = '?';

switch ($var) {
    case '@' : 
        return true;
    case '!' :
        return false;
    default:
        UNREACHABLE();
}

UNREACHABLE('Error message.');

?>
--EXPECTF--
<?php

class FlowError extends \Error {}

$var = '?';

switch ($var) {
    case '@' : 
        return true;
    case '!' :
        return false;
    default:
        throw new \FlowError('Unreachable point reached.');
}

throw new \FlowError('Error message.');

?>
