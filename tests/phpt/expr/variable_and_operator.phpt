--TEST--
test variables and operators expressions
--FILE--
<?php

macro {
   ·expr()·my_expr
} >> {
expression {
    ·my_expr
}
}

$a = 42
$a + 42
$a - 42
$a * 42
$a / 42
$a % 42
$a ^ 42
$a | 42
$a & 42
$a ** 42
$b = &$a
$a += 1
$a >> 42
$a << 42
$a += 42
$a -= 42
$a *= 42
$a **= 42
$a /= 42
$a .= 42
$a %= 42
$a &= 42
$a |= 42
$a ^= 42
$a <<= 42
$a >>= 42
$a == 42
$a === 42
$a >= 42
$a != 42
$a !== 42
$a <= 42
$a == 42
$a <=> 42

?>
--EXPECTF--
<?php

expression {
    $a=42
}
expression {
    $a+42
}
expression {
    $a-42
}
expression {
    $a*42
}
expression {
    $a/42
}
expression {
    $a%42
}
expression {
    $a^42
}
expression {
    $a|42
}
expression {
    $a&42
}
expression {
    $a**42
}
expression {
    $b=&$a
}
expression {
    $a+=1
}
expression {
    $a>>42
}
expression {
    $a<<42
}
expression {
    $a+=42
}
expression {
    $a-=42
}
expression {
    $a*=42
}
expression {
    $a**=42
}
expression {
    $a/=42
}
expression {
    $a.=42
}
expression {
    $a%=42
}
expression {
    $a&=42
}
expression {
    $a|=42
}
expression {
    $a^=42
}
expression {
    $a<<=42
}
expression {
    $a>>=42
}
expression {
    $a==42
}
expression {
    $a===42
}
expression {
    $a>=42
}
expression {
    $a!=42
}
expression {
    $a!==42
}
expression {
    $a<=42
}
expression {
    $a==42
}
expression {
    $a<=>42
}

?>