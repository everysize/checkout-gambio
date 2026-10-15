<?php
/* --------------------------------------------------------------
   EverysizeCheckoutOrderMailer.inc.php

   Sends the regular Gambio order confirmation for an imported order
   (settings: "Bestellbestätigung senden"), exactly like the shop's
   own gm_send_order.php: the OrderMailBuilder template, the
   configured attachments (AGB, privacy notice, withdrawal info and
   form) via ShopContentContentControl, then gm_send_order_status=1
   with gm_order_send_date, a status history entry with
   customer_notified=1 and the module's confirmation_sent_at stamp.
   A failure never invalidates the import — the caller logs it.

   sendPending() picks up confirmations that stayed pending (CLI
   imports); the cronjob task calls it in the full shop context.
   --------------------------------------------------------------*/

require_once __DIR__ . '/../../src/autoload.php';

class EverysizeCheckoutOrderMailer
{
    /** Configuration keys turned into constants before mailing. */
    private const CONFIG_KEYS = [
        'SEND_EMAILS', 'EMAIL_BILLING_ADDRESS', 'EMAIL_BILLING_NAME',
        'EMAIL_BILLING_REPLY_ADDRESS', 'EMAIL_BILLING_REPLY_ADDRESS_NAME',
        'EMAIL_FROM', 'STORE_OWNER', 'STORE_NAME',
        'EMAIL_BILLING_FORWARDING_STRING',
    ];

    private EverysizeCheckoutDb $db;

    private EverysizeCheckoutSettings $settings;

    private ?EverysizeCheckoutProtocol $protocol;

    public function __construct(
        EverysizeCheckoutDb $db,
        EverysizeCheckoutSettings $settings,
        ?EverysizeCheckoutProtocol $protocol = null
    ) {
        $this->db = $db;
        $this->settings = $settings;
        $this->protocol = $protocol;
    }


    /**
     * Sends the confirmation for one imported order.
     *
     * @param string $ordersId Gambio order number
     *
     * @return bool true when sent (or sending disabled), false when nothing was sent
     */
    public function sendConfirmation(string $ordersId): bool
    {
        $this->bootLegacyMailLayer();

        if (\defined('SEND_EMAILS') && SEND_EMAILS !== 'true') {
            return false;
        }

        $order = $this->db->fetchRows(
            'SELECT orders_id, orders_status, customers_email_address, customers_firstname, customers_lastname,
                    customers_status, language, date_purchased
             FROM orders WHERE orders_id = :id',
            [':id' => $ordersId]
        );
        if ($order === []) {
            return false;
        }
        $order = $order[0];

        // The order language drives template, subject and attachments.
        $languageId = (string) ($this->db->fetchValue(
            'SELECT languages_id FROM languages WHERE directory = :directory LIMIT 1',
            [':directory' => (string) $order['language']]
        ) ?? '2');

        $builder = MainFactory::create('OrderMailBuilder');
        $orderData = $builder->getOrderData((int) $ordersId);
        if ($orderData === []) {
            return false;
        }
        $builder->build($orderData);

        $customerName = trim((string) $order['customers_firstname'] . ' ' . (string) $order['customers_lastname']);
        $attachments = $this->attachments((string) $order['language'], (string) $order['customers_status']);

        $sent = (bool) xtc_php_mail(
            \defined('EMAIL_FROM') ? EMAIL_FROM : '',
            \defined('STORE_NAME') ? STORE_NAME : '',
            (string) $order['customers_email_address'],
            $customerName,
            \defined('EMAIL_BILLING_FORWARDING_STRING') ? EMAIL_BILLING_FORWARDING_STRING : '',
            \defined('EMAIL_BILLING_REPLY_ADDRESS') ? EMAIL_BILLING_REPLY_ADDRESS : '',
            \defined('EMAIL_BILLING_REPLY_ADDRESS_NAME') ? EMAIL_BILLING_REPLY_ADDRESS_NAME : '',
            $attachments,
            '',
            $this->subject((string) $ordersId, (string) $order['customers_firstname'], (string) $order['customers_lastname'], $languageId),
            $builder->getHtmlMailBody(),
            $builder->getTxtMailBody()
        );
        if (!$sent) {
            $this->protocol?->add(
                'Order confirmation not sent',
                sprintf('Order %s: xtc_php_mail returned false (SMTP/mail() layer).', $ordersId),
                false
            );
            return false;
        }

        // Same bookkeeping as gm_send_order.php, plus the module stamp that
        // keeps pending confirmations idempotent.
        $this->db->execute(
            'UPDATE orders SET gm_send_order_status = 1, gm_order_send_date = NOW() WHERE orders_id = :id',
            [':id' => $ordersId]
        );
        $this->db->execute(
            'INSERT INTO orders_status_history (orders_id, orders_status_id, date_added, customer_notified, comments, customer_id)
             VALUES (:ordersId, :statusId, NOW(), 1, :comments, 0)',
            [
                ':ordersId' => $ordersId,
                ':statusId' => $order['orders_status'],
                ':comments' => 'Bestellbestätigung versendet (everysize).',
            ]
        );
        $this->db->execute(
            'UPDATE everysize_checkout_order SET confirmation_sent_at = NOW() WHERE orders_id = :id',
            [':id' => $ordersId]
        );

        return true;
    }


    /**
     * Sends all confirmations that stayed pending (e.g. imported from the
     * CLI). Only orders that have never been sent (gm_send_order_status=0)
     * and are not cancelled are picked up, so a manually sent order or a
     * revoked everysize order is never mailed twice or at all.
     *
     * @return int number of confirmations sent
     */
    public function sendPending(): int
    {
        if (!$this->settings->sendOrderConfirmation()) {
            return 0;
        }
        $this->bootLegacyMailLayer();
        if (\defined('SEND_EMAILS') && SEND_EMAILS !== 'true') {
            return 0;
        }

        $cancelStatuses = $this->settings->getCancellationStatuses();
        $placeholders = [];
        $params = [':sent' => '0'];
        $notIn = '';
        if ($cancelStatuses !== []) {
            $parts = [];
            foreach (array_values($cancelStatuses) as $i => $status) {
                $key = ':cancel' . $i;
                $parts[] = $key;
                $params[$key] = (string) $status;
            }
            $notIn = ' AND o.orders_status NOT IN (' . implode(', ', $parts) . ')';
        }
        $sent = 0;
        foreach ($this->db->fetchRows(
            'SELECT eco.orders_id FROM everysize_checkout_order eco
             JOIN orders o ON o.orders_id = eco.orders_id
             WHERE eco.confirmation_sent_at IS NULL AND o.gm_send_order_status = :sent' . $notIn,
            $params
        ) as $row) {
            try {
                if ($this->sendConfirmation((string) $row['orders_id'])) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                // A silently swallowed failure would leave the order pending forever
                // with no trace; the protocol records why it stayed pending.
                $this->protocol?->add(
                    'Order confirmation not sent',
                    sprintf(
                        'Order %d: %s in %s:%d',
                        $row['orders_id'],
                        $e->getMessage(),
                        basename($e->getFile()),
                        $e->getLine()
                    ),
                    false
                );
            }
        }

        return $sent;
    }


    /** The configured attachments (AGB, privacy notice, withdrawal), like gm_send_order.php. */
    private function attachments(string $languageDirectory, string $customerStatus): array
    {
        $attachments = [];
        if (!\function_exists('gm_get_conf')) {
            return $attachments;
        }

        $contentControl = MainFactory::create_object('ShopContentContentControl');
        $contentControl->set_language_id((int) ($this->db->fetchValue(
            'SELECT languages_id FROM languages WHERE directory = :directory LIMIT 1',
            [':directory' => $languageDirectory]
        ) ?? '2'));
        $contentControl->set_customer_status_id((int) $customerStatus);

        $add = static function ($flag, callable $configure) use (&$attachments, $contentControl): void {
            if (gm_get_conf($flag) == '1') {
                $configure($contentControl);
                $file = $contentControl->get_file();
                if ($file !== null && $file !== '') {
                    $attachments[] = $file;
                }
            }
        };
        $add('ATTACH_CONDITIONS_OF_USE_IN_ORDER_CONFIRMATION', static function ($c): void {
            $c->set_content_group('3');
        });
        $add('ATTACH_PRIVACY_NOTICE_IN_ORDER_CONFIRMATION', static function ($c): void {
            $c->set_content_group('2');
        });
        $add('ATTACH_WITHDRAWAL_INFO_IN_ORDER_CONFIRMATION', static function ($c): void {
            $c->set_content_group((string) gm_get_conf('GM_WITHDRAWAL_CONTENT_ID'));
        });
        $add('ATTACH_WITHDRAWAL_FORM_IN_ORDER_CONFIRMATION', static function ($c): void {
            $c->set_content_group((string) gm_get_conf('GM_WITHDRAWAL_CONTENT_ID'));
            $c->set_withdrawal_form('1');
        });

        return $attachments;
    }


    private function subject(string $ordersId, string $firstName, string $lastName, string $languageId): string
    {
        $template = 'Ihre Bestellung {$nr} vom {$date}';
        if (\function_exists('gm_get_content')) {
            $content = gm_get_content('EMAIL_BILLING_SUBJECT_ORDER', (int) $languageId);
            if (\is_string($content) && $content !== '') {
                $template = $content;
            }
        }

        return str_replace(
            ['{$nr}', '{$date}', '{$lastname}', '{$firstname}'],
            [$ordersId, date('d.m.Y'), $lastName, $firstName],
            $template
        );
    }


    /** Legacy pieces the checkout boot normally provides. */
    private function bootLegacyMailLayer(): void
    {
        // OrderMailBuilder talks to the old database layer ($GLOBALS['db_link']).
        // require_once: some of these files pull each other in (xtc_db_connect
        // loads xtc_db_error) and the admin boot may already have loaded them.
        if (!\function_exists('xtc_db_query')) {
            require_once DIR_FS_INC . 'xtc_db_connect.inc.php';
            require_once DIR_FS_INC . 'xtc_db_query.inc.php';
            require_once DIR_FS_INC . 'xtc_db_fetch_array.inc.php';
            require_once DIR_FS_INC . 'xtc_db_num_rows.inc.php';
            require_once DIR_FS_INC . 'xtc_db_insert_id.inc.php';
            require_once DIR_FS_INC . 'xtc_db_error.inc.php';
            // The wrappers and check_data_type come with the admin boot before any old-class code runs.
            foreach (['htmlentities_wrapper', 'htmlspecialchars_wrapper', 'html_entity_decode_wrapper',
                      'parse_str_wrapper', 'strlen_wrapper', 'substr_wrapper', 'strpos_wrapper',
                      'strrpos_wrapper', 'strtolower_wrapper', 'strtoupper_wrapper',
                      'substr_count_wrapper', 'utf8_encode_wrapper'] as $wrapper) {
                require_once DIR_FS_INC . $wrapper . '.inc.php';
            }
            require_once DIR_FS_CATALOG . 'gm/inc/check_data_type.inc.php';
            xtc_db_connect() || xtc_db_error(DB_SERVER, DB_SERVER_USERNAME, DB_SERVER_PASSWORD);
        }
        if (!\function_exists('xtc_php_mail')) {
            require_once DIR_FS_INC . 'xtc_php_mail.inc.php';
        }
        if (!\function_exists('gm_get_content')) {
            require_once DIR_FS_CATALOG . 'gm/inc/gm_get_content.inc.php';
        }
        if (!\function_exists('gm_get_conf')) {
            require_once DIR_FS_CATALOG . 'gm/inc/gm_get_conf.inc.php';
        }

        foreach (self::CONFIG_KEYS as $key) {
            if (\defined($key)) {
                continue;
            }
            $value = $this->db->fetchValue(
                'SELECT `value` FROM gx_configurations WHERE `key` = :key',
                [':key' => 'configuration/' . $key]
            );
            \define($key, $value ?? '');
        }
    }
}
