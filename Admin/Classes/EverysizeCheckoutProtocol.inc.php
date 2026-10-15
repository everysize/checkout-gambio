<?php
/* --------------------------------------------------------------
   EverysizeCheckoutProtocol.inc.php

   Module protocol in everysize_checkout_protocol: one row per sync
   event (state ok/error), cleanup after 31 days keeping at most the
   newest 1000 rows.
   --------------------------------------------------------------*/

require_once __DIR__ . '/../../src/autoload.php';

use Everysize\Checkout\Protocol;

class EverysizeCheckoutProtocol implements Protocol
{
    /** Rows kept after a cleanup run, newest first. */
    public const MAX_ROWS = 1000;

    private EverysizeCheckoutDb $db;

    public function __construct(EverysizeCheckoutDb $db)
    {
        $this->db = $db;
    }


    public function add(string $title, string $message, bool $success): void
    {
        $this->db->execute(
            'INSERT INTO everysize_checkout_protocol (title, message, state, created_at)
             VALUES (:title, :message, :state, NOW())',
            [
                ':title'   => mb_substr($title, 0, 255),
                ':message' => $message,
                ':state'   => $success ? 'ok' : 'error',
            ]
        );
    }


    public function cleanup(int $days = Protocol::RETENTION_DAYS): void
    {
        // The interval argument cannot be a placeholder in MySQL.
        $this->db->execute(
            sprintf('DELETE FROM everysize_checkout_protocol WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)', max(1, $days))
        );
        $this->db->execute(
            sprintf(
                'DELETE FROM everysize_checkout_protocol WHERE id NOT IN (
                    SELECT id FROM (SELECT id FROM everysize_checkout_protocol ORDER BY id DESC LIMIT %d) keep
                )',
                self::MAX_ROWS
            )
        );
    }
}
