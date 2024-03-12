<?php

// Disable direct access
defined('ABSPATH') || exit;
// Define lib name
define('LINKATE_DEBUG', true);

function _cherry_debug($func, $variable, $description = '')
{
    $options = get_option('linkate-posts', []);
    if ($options['debug_enabled'] === "true") {
        echo "FUNC: " . $func . PHP_EOL;
        if ($description)
            echo $description . PHP_EOL;
        echo '<pre>';
        var_dump($variable);
        echo '</pre>';
    }
}
