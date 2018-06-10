--TEST--
Some macros
--FILE--
<?php

$(macro) {

    block { $(layer() as body) }

} >> {

    test {
    $(body)
    }

}

block {
    // random block of code
    $foo->reset();
    while ($current = $foo->current()) {
        if ($meta = $current->meta()) {
            if (! $meta[2]) {
                throw new Exception("Foo {$x->y()}");
            }
            $current->__construct([$meta[1], null, null]);
            $current->tag('crossover', $meta[0]);
        }

        $foo->next();
    }
    $foo->reset();
}

?>
--EXPECTF--
<?php

test {
    $foo->reset();
    while ($current = $foo->current()) {
        if ($meta = $current->meta()) {
            if (! $meta[2]) {
                throw new Exception("Foo {$x->y()}");
            }
            $current->__construct([$meta[1], null, null]);
            $current->tag('crossover', $meta[0]);
        }

        $foo->next();
    }
    $foo->reset();

    }

?>
