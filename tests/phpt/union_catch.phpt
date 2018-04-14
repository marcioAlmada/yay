--TEST--
Union exception catch --pretty-print

Reference https://wiki.php.net/rfc/multiple-catch

--FILE--
<?php

$(macro) {
    catch($(ls(ns() as type, token('|')) as types) $(T_VARIABLE as exception_var)) $({...} as body)
} >> {
    $(types ... {
        catch($(type) $(exception_var)) {
            $(body)
        }
    })
}

try {
    throw new FooException();
} catch (\BarException | FooException $e) {
    doSomething();
    throw $e;
} catch (\Exception $e) {
    doSomethingElse();
}

?>
--EXPECTF--
<?php

try {
    throw new FooException();
} catch (\BarException $e) {
    doSomething();
    throw $e;
} catch (FooException $e) {
    doSomething();
    throw $e;
} catch (\Exception $e) {
    doSomethingElse();
}

?>
