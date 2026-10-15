<?php

/**
 * Registers an autoloader for the everysize checkout core (src/). GXModules
 * are not composer-installed in the shop, so the Gambio layer pulls in its
 * namespaced classes through this file (required by every module class that
 * touches the core). Safe to require multiple times.
 */

if (!\function_exists('everysize_checkout_autoload')) {
    function everysize_checkout_autoload(string $class): void
    {
        $prefix = 'Everysize\\Checkout\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }

    \spl_autoload_register('everysize_checkout_autoload');
}
