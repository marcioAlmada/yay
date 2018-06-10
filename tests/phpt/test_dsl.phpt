--TEST--
Example DSL for testing --pretty-print
--FILE--
<?php

$(macro :recursion) {
    $(identifier() as procedure)  $(T_CONSTANT_ENCAPSED_STRING as description) $({...} as body)
} >> {
    $(procedure)($(description), function() {$(body)});
}

$(macro) {
    $(identifier() as procedure) $({...} as body)
} >> {
    $(procedure)(function() {$(body)});
}

describe 'ArrayObject' {
    beforeEach {
        $this->arrayObject = new ArrayObject([1, 2, 3]);
    }
    describe '->count()' {
        it 'should return the number of items' {
            assert($this->arrayObject->count() === 3, 'expected 3');
        }
    }
}

?>
--EXPECTF--
<?php

describe('ArrayObject', function () {
    beforeEach(function () {
        $this->arrayObject = new ArrayObject([1, 2, 3]);
    });
    describe('->count()', function () {
        it('should return the number of items', function () {
            assert($this->arrayObject->count() === 3, 'expected 3');
        });
    });
});

?>
