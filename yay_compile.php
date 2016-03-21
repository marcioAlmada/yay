<?php declare(strict_types=1);

function yay_compile(string $file) {
    $source = explode('return \yay_compile(__FILE__); __halt_compiler();', file_get_contents($file));
    $tmp = tempnam(sys_get_temp_dir(), 'yay'. $file);
    file_put_contents($tmp, \yay_parse( '<?php' . end($source)));
    return include "yay://{$tmp}";
}
