--TEST--
Union exception catch --pretty-print

Reference https://wiki.php.net/rfc/multiple-catch

--FILE--
<?php

macro {
    catch(·ls(·ns()·type, ·token('|'))·types T_VARIABLE·exception_var) {
        ···body
    }
} >> {
    ·types ··· {
        catch(·type T_VARIABLE·exception_var) {
            ···body
        }
    }
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
