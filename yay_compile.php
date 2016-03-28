<?php declare(strict_types=1);

function yay_compile(string $file, string $dir = null) {
    $source =
        explode(
            'return \yay_compile(__FILE__); __halt_compiler();',
            file_get_contents($file)
        )[1]
    ;
    $tmpfile = ($dir ?: sys_get_temp_dir()) . '/yay' . str_replace(['/', '\\'], '-', $file);
    file_put_contents($tmpfile, \yay_parse( '<?php' . $source));

    return include "yay://{$tmpfile}";
}
