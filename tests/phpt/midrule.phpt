--TEST--
Check that midrule works
--FILE--
<?php

$(macro) {
    $(
        chain(
            attempt,
            midrule(function($stream) {
                $index = $stream->index();

                if (in_array(strtolower($stream->current()), ["true", "false", "null"])) {
                    return new \Yay\Error(null, null, $stream->last());
                }

                return new \Yay\Ast;
            }),
            ns() as ns
        )
        as x
    )
} >> {
    mateched $(x[ns])
}


attempt true
attempt false
attempt null

attempt strtoupper
attempt ucwords

?>
--EXPECTF--
<?php

attempt true
attempt false
attempt null

mateched strtoupper
mateched ucwords

?>
