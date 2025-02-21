<?php

/**
 * WordPress-style autoloader for PHPUnit.
 *
 * @param string $class_name The name of the class to be loaded.
 * @return void
 */
function wpss_autoloader($class_name)
{
    $class_file = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    $directories = [
        dirname(__FILE__) . '/includes/',
        dirname(__FILE__) . '/tests/'
    ];

    foreach ($directories as $directory) {
        $file_path = $directory . $class_file;
        if (file_exists($file_path)) {
            require_once $file_path;
            return;
        }
    }
}

spl_autoload_register('wpss_autoloader');
