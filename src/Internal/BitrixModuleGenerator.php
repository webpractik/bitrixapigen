<?php

namespace Webpractik\Bitrixapigen\Internal;

class BitrixModuleGenerator
{

    public static function generate($options): void
    {
        $d = $options['directory'] . '/../'; // exit from lib

        if (!file_exists($d . 'include.php')) {
            touch($d . 'include.php');
            file_put_contents($d . 'include.php', ' ', FILE_APPEND);
        }

    }
}
