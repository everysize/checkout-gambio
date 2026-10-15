<?php
/* --------------------------------------------------------------
   EverysizeCheckoutFactory.inc.php

   Builds the module core's services for the Gambio layer (cronjob and
   CLI): one mysqli-backed Db, the configuration-storage settings, the
   API client (URL from the environment via ApiUrl::resolve, never a
   silent fallback to production) and the sync with the Gambio order
   importer. Credentials missing → Sync itself does nothing and exits 0.
   --------------------------------------------------------------
*/

require_once __DIR__ . '/../../src/autoload.php';

use Everysize\Checkout\Api\ApiUrl;
use Everysize\Checkout\Api\Client;
use Everysize\Checkout\Export\StatusExporter;
use Everysize\Checkout\ModuleInfo;
use Everysize\Checkout\OrderLinks;
use Everysize\Checkout\Sync;

class EverysizeCheckoutFactory
{
    public static function db(): EverysizeCheckoutDb
    {
        return EverysizeCheckoutDb::fromShopDefines();
    }


    public static function client(EverysizeCheckoutSettings $settings, ?EverysizeCheckoutDb $db = null): Client
    {
        $installed = null;
        if ($db !== null) {
            $installed = $db->fetchValue(
                'SELECT `value` FROM gx_configurations WHERE `key` = :key',
                [':key' => 'gm_configuration/INSTALLED_VERSION']
            );
        }

        return new Client(
            $settings->getClientId(),
            $settings->getClientSecret(),
            ApiUrl::resolve(null),
            ModuleInfo::shopSystemVersion($installed !== null ? (string) $installed : null),
            ModuleInfo::VERSION
        );
    }


    /** All services wired; $db shared so importer, sync and factory use one connection. */
    public static function sync(?EverysizeCheckoutDb $db = null): Sync
    {
        $db = $db ?? self::db();
        $settings = new EverysizeCheckoutSettings();
        $client = self::client($settings, $db);
        $links = new OrderLinks($db, $settings);
        $importer = new EverysizeCheckoutOrderImporter($db, $settings, $links, self::mailer($db));
        $exporter = new StatusExporter($client, $settings, $links);
        $protocol = new EverysizeCheckoutProtocol($db);

        return new Sync($settings, $client, $db, $importer, $exporter, $links, $protocol);
    }

    /** The order confirmation mailer (pending confirmations after CLI imports). */
    public static function mailer(?EverysizeCheckoutDb $db = null): EverysizeCheckoutOrderMailer
    {
        $db = $db ?? self::db();

        return new EverysizeCheckoutOrderMailer($db, new EverysizeCheckoutSettings(), new EverysizeCheckoutProtocol($db));
    }
}
