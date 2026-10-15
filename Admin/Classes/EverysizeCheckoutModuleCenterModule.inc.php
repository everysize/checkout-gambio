<?php
/* --------------------------------------------------------------
   EverysizeCheckoutModuleCenterModule.inc.php

   everysize Checkout — Module Center module. Installing creates the
   module tables (idempotent) and remembers the schema version;
   deinstalling keeps all data (tables, credentials, links) like the
   other everysize plugins do.

   Extends GXModuleCenterModule: that is what makes the shop treat
   this GXModule as installed — the parent install() sets the legacy
   gm_configuration/MODULE_CENTER_…_INSTALLED flag AND the
   modules/EverysizeCheckout/active flag, which GXModulesCache needs
   to include this module's files (overloads, controllers) at all.
   --------------------------------------------------------------
*/

require_once __DIR__ . '/../../src/autoload.php';

class EverysizeCheckoutModuleCenterModule extends GXModuleCenterModule
{
    /** Schema version the install() routine brings the tables up to. */
    public const SCHEMA_VERSION = 2;

    /** Idempotent DDL, keyed by schema version that introduced the statement. */
    private const TABLES = [
        1 => [
            'CREATE TABLE IF NOT EXISTS `everysize_checkout_order` (
                `orders_id` INT(11) NOT NULL,
                `everysize_order_id` VARCHAR(255) NOT NULL,
                `paypal_capture_id` VARCHAR(255) NOT NULL DEFAULT \'\',
                `original_email` VARCHAR(255) NOT NULL DEFAULT \'\',
                `number_reported` DATETIME NULL DEFAULT NULL,
                `confirmation_sent_at` DATETIME NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`orders_id`),
                UNIQUE KEY `everysize_order_id` (`everysize_order_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `everysize_checkout_order_item` (
                `orders_id` INT(11) NOT NULL,
                `orders_products_id` INT(11) NOT NULL,
                `sku` VARCHAR(255) NOT NULL,
                KEY `orders_id` (`orders_id`),
                KEY `sku` (`sku`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `everysize_checkout_report` (
                `orders_id` INT(11) NOT NULL,
                `kind` VARCHAR(32) NOT NULL,
                `sku` VARCHAR(255) NOT NULL DEFAULT \'\',
                `reason` VARCHAR(255) NOT NULL DEFAULT \'\',
                `result` VARCHAR(32) NOT NULL,
                `created_at` DATETIME NOT NULL,
                UNIQUE KEY `report_once` (`orders_id`, `kind`, `sku`),
                KEY `orders_id` (`orders_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `everysize_checkout_protocol` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) NOT NULL DEFAULT \'\',
                `message` TEXT NOT NULL,
                `state` VARCHAR(16) NOT NULL DEFAULT \'\',
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4',
        ],
        2 => [
            // Pending order confirmations (sent by the cronjob, not the CLI).
            'ALTER TABLE `everysize_checkout_order`
                ADD COLUMN `confirmation_sent_at` DATETIME NULL DEFAULT NULL AFTER `number_reported`',
        ],
    ];

    protected function _init()
    {
        $this->title       = $this->languageTextManager->get_text('everysizecheckout_title');
        $this->description = $this->languageTextManager->get_text('everysizecheckout_description');
        $this->sortOrder   = 59455;
        $this->_setModuleName();
    }


    /**
     * Creates the module tables (idempotent — a second install or an update
     * does not touch existing data or credentials) and stores the schema
     * version the shop is now on.
     *
     * @return void
     */
    public function install(): void
    {
        $this->applySchemaUpdates();

        // One switch: the cronjob's "active" flag starts in sync with the
        // module's "automatic sync" setting (default on), so the cronjob
        // works without a detour through the toolbox.
        $this->db->replace('gx_configurations', [
            'key'   => 'cronjobs/EverysizeCheckout/active',
            'value' => 'true',
        ]);

        parent::install();
    }


    /**
     * Deinstalls the module but keeps all data (tables, credentials and
     * links stay exactly like in the other everysize plugins).
     *
     * @return void
     */
    public function uninstall(): void
    {
        parent::uninstall();
    }


    /**
     * Runs all DDL newer than the stored schema version and stores the new
     * version. Called from install() and from the update routine (the
     * Module Center shows an update action once version_info.php reports a
     * newer module version); safe to call at any time.
     *
     * @return void
     */
    public function applySchemaUpdates(): void
    {
        $current = (int) $this->db->select('value')
            ->from('gx_configurations')
            ->where('`key`', 'gm_configuration/EVERYSIZE_CHECKOUT_SCHEMA_VERSION')
            ->get()
            ->row()->value ?? 0;

        foreach (self::TABLES as $version => $statements) {
            if ($version > $current) {
                foreach ($statements as $ddl) {
                    // A fresh install already creates the tables with the v2
                    // column; the ALTER would fail on it — skip when present.
                    if ($version === 2 && $this->columnExists('everysize_checkout_order', 'confirmation_sent_at')) {
                        continue;
                    }
                    $this->db->query($ddl);
                }
            }
        }

        $this->db->replace('gx_configurations', [
            'key'   => 'gm_configuration/EVERYSIZE_CHECKOUT_SCHEMA_VERSION',
            'value' => (string) self::SCHEMA_VERSION,
        ]);
    }


    private function columnExists(string $table, string $column): bool
    {
        return $this->db->query(
            "SHOW COLUMNS FROM `$table` LIKE '$column'"
        )->num_rows() > 0;
    }
}
