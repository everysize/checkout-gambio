<?php
/* --------------------------------------------------------------
   bin/sync.php

   CLI entry point for the everysize checkout sync, e.g. for a system
   cron or manual runs:  php GXModules/Everysize/Checkout/bin/sync.php

   Boots the shop the minimal way (defines, old core layer, legacy
   dependency container — the same services the module center uses),
   then runs one sync: imports open everysize orders, reports
   shipping/cancellation/return/refund states and sends order
   confirmations that stayed pending. Credentials missing → clean
   no-op (exit 0). Output is plain text; --json for machines.

   The module's "automatic sync" switch is respected: disabled →
   no-op, --force runs anyway (for tests). Exit code: 0 when the run
   had no errors, 1 otherwise.
   --------------------------------------------------------------*/

declare(strict_types=1);

// GXModules/Everysize/Checkout/bin → shop root
$shopRoot = dirname(__DIR__, 4);

if (\PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("everysize checkout sync: CLI only\n");
}

if (!\defined('APPLICATION_RUN_MODE')) {
    // The shop kernel branches on 'backend'/'frontend' only; CLI behaves like frontend.
    \define('APPLICATION_RUN_MODE', 'frontend');
}

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '1');

require $shopRoot . '/includes/configure.php';
require $shopRoot . '/system/gngp_layer_init.inc.php';
require $shopRoot . '/gm/inc/check_data_type.inc.php'; // needed by CachedDirectory during the first class scan
require $shopRoot . '/system/classes/ConfigurationStorage.inc.php';
require $shopRoot . '/system/core/logging/LogControl.inc.php';
require $shopRoot . '/GXMainComponents/Loaders/GXCoreLoader/StaticGXCoreLoader.inc.php';
require $shopRoot . '/GXMainComponents/Shared/LegacyDependencyContainer.php';

// The module's own layers (src autoloads itself).
require __DIR__ . '/../Admin/Classes/EverysizeCheckoutDb.inc.php';
require __DIR__ . '/../Admin/Classes/EverysizeCheckoutSettings.inc.php';
require __DIR__ . '/../Admin/Classes/EverysizeCheckoutProtocol.inc.php';
require __DIR__ . '/../Admin/Classes/EverysizeCheckoutOrderImporter.inc.php';
require __DIR__ . '/../Admin/Classes/EverysizeCheckoutOrderMailer.inc.php';
require __DIR__ . '/../Admin/Classes/EverysizeCheckoutFactory.inc.php';

$json = \in_array('--json', $_SERVER['argv'] ?? [], true);
$force = \in_array('--force', $_SERVER['argv'] ?? [], true);

try {
    $settings = new EverysizeCheckoutSettings();
    if (!$force && !$settings->isSyncEnabled()) {
        $result = ['messages' => ['sync disabled in the module settings (use --force to run anyway)'], 'errors' => []];
    } else {
        $sync = EverysizeCheckoutFactory::sync();
        $result = $sync->run();
    }
} catch (Throwable $e) {
    $result = ['messages' => [], 'errors' => ['sync failed: ' . $e->getMessage()]];
}

if ($json) {
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
} else {
    foreach ($result['messages'] ?? [] as $message) {
        echo $message, "\n";
    }
    foreach ($result['errors'] ?? [] as $error) {
        echo 'ERROR: ', $error, "\n";
    }
    if (($result['messages'] ?? []) === [] && ($result['errors'] ?? []) === []) {
        echo "nothing to do\n";
    }
}

exit(($result['errors'] ?? []) === [] ? 0 : 1);
