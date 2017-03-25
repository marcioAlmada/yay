--TEST--
Check that midrule works
--FILE--
<?php

macro {
    ·chain(
        matched,
        ·midrule(function($stream) {
            $index = $stream->index();

            if (in_array(strtolower($stream->current()), ["true", "false", "null"])) {
                return new \Yay\Error(null, null, $stream->last());
            }

            return new \Yay\Ast;
        }),
        ·ns()·ns
    )
} >> {
    replaced ·ns
}

matched true
matched false
matched null

matched strtoupper
matched ucwords

?>
--EXPECTF--
<?php

matched true
matched false
matched null

replaced strtoupper
replaced ucwords

?>
