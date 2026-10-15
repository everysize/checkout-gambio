<?php
/* --------------------------------------------------------------
   everysizecheckout.lang.inc.php (english)
   Section "everysizecheckout": order detail box and protocol page.
   All keys are everysizecheckout_-prefixed so Gambio never
   translates them itself.
   --------------------------------------------------------------
*/

$t_language_text_section_content_array = [
    // Box sections
    'everysizecheckout_section_order'   => 'everysize order',
    'everysizecheckout_section_refund'  => 'Refund via everysize',
    'everysizecheckout_section_reports' => 'Reports to everysize',

    // Info table
    'everysizecheckout_es_order_number'    => 'everysize order number',
    'everysizecheckout_imported'           => 'Imported',
    'everysizecheckout_number_reported'    => 'Order number reported to everysize',
    'everysizecheckout_not_yet'            => 'not yet',
    'everysizecheckout_paypal_capture'     => 'PayPal transaction (capture ID)',
    'everysizecheckout_original_email'     => 'Customer\'s original e-mail',
    'everysizecheckout_original_email_hint' => '(for your information only — shop mails go to the order\'s everysize forwarding address)',

    // Refund
    'everysizecheckout_paid'            => 'Paid',
    'everysizecheckout_refunded'        => 'refunded',
    'everysizecheckout_open'            => 'still open',
    'everysizecheckout_date'            => 'Date',
    'everysizecheckout_refund_amount'   => 'Refunded amount',
    'everysizecheckout_refund_status'   => 'Status',
    'everysizecheckout_refund_id'       => 'Refund ID',
    'everysizecheckout_amount'          => 'Amount (EUR)',
    'everysizecheckout_refund_button'   => 'Refund',
    'everysizecheckout_refund_confirm'  => 'Refund %s to the customer via everysize? This cannot be undone.',
    'everysizecheckout_hint_refund'     => 'The refund runs through everysize: the amount goes back through the PayPal account the customer paid with. No PayPal credentials in the shop.',
    'everysizecheckout_hint_autorefund' => 'A cancellation before shipping without any refund is refunded automatically in full (setting under Module Center → everysize Checkout).',
    'everysizecheckout_refunds_error'   => 'Refunds could not be loaded:',
    'everysizecheckout_refund_ok'       => 'Refund via everysize initiated.',
    'everysizecheckout_refund_failed'   => 'Refund failed:',
    'everysizecheckout_refund_invalid'  => 'Please enter a valid amount greater than 0.',

    // Reports
    'everysizecheckout_report_none'      => 'None yet.',
    'everysizecheckout_report_time'      => 'Time',
    'everysizecheckout_report_kind'      => 'Report',
    'everysizecheckout_report_item'      => 'Item / tracking code / refund',
    'everysizecheckout_report_reason'    => 'Reason / amount',
    'everysizecheckout_report_result'    => 'Result',
    'everysizecheckout_kind_fulfillment' => 'Shipping',
    'everysizecheckout_kind_tracking'    => 'Tracking code',
    'everysizecheckout_kind_revocation'  => 'Cancellation',
    'everysizecheckout_kind_return'      => 'Return',
    'everysizecheckout_kind_refund'      => 'Refund',
    'everysizecheckout_result_sent'      => 'sent',
    'everysizecheckout_result_known'     => 'already known',
    'everysizecheckout_result_skipped'   => 'skipped',
    'everysizecheckout_auto_refund_sku'  => 'automatic, cancellation before shipping',

    // Protocol page
    'everysizecheckout_protocol_title'   => 'everysize protocol',
    'everysizecheckout_protocol_empty'   => 'No entries yet.',
    'everysizecheckout_protocol_time'    => 'Time',
    'everysizecheckout_protocol_title_col' => 'Report',
    'everysizecheckout_protocol_message' => 'Message',
    'everysizecheckout_protocol_state'   => 'Result',

    // Order overview (column via OrdersOverviewColumns overload)
    'everysizecheckout_overview_column'  => 'everysize order no.',

    // Protocol page: filter and pagination
    'everysizecheckout_protocol_filter_all'   => 'All',
    'everysizecheckout_protocol_filter_ok'    => 'OK',
    'everysizecheckout_protocol_filter_error' => 'Errors',
    'everysizecheckout_protocol_pager'        => 'Page %1$d of %2$d',

    // Settings page (duplicate of the module_center_module section, which the
    // admin context does not load automatically)
    'everysizecheckout_title'                 => 'everysize Checkout',
    'everysizecheckout_saved'                 => 'Settings saved.',
    'everysizecheckout_credentials'           => 'Credentials',
    'everysizecheckout_client_id'             => 'Client ID',
    'everysizecheckout_client_secret'         => 'Client secret',
    'everysizecheckout_secret_stored'         => 'stored (enter a new one to change, leave empty to keep)',
    'everysizecheckout_test_connection'       => 'Test connection',
    'everysizecheckout_testing'               => 'Testing connection …',
    'everysizecheckout_test_ok'               => 'Connection OK. Shop ID:',
    'everysizecheckout_test_failed'           => 'Connection failed:',
    'everysizecheckout_no_credentials'        => 'No credentials stored (please enter and save them).',
    'everysizecheckout_remove_credentials'    => 'Remove credentials',
    'everysizecheckout_remove_confirm'        => 'Really remove the credentials? Orders and reports are kept.',
    'everysizecheckout_credentials_removed'   => 'Credentials removed.',
    'everysizecheckout_env_production'        => 'Environment: production.',
    'everysizecheckout_env_test'              => 'Environment: test environment.',
    'everysizecheckout_env_invalid'           => 'The API URL is invalid, the module will not call the API:',
    'everysizecheckout_import'                => 'Import',
    'everysizecheckout_import_status'         => 'Order status for new orders',
    'everysizecheckout_send_confirmation'     => 'Send order confirmation to the customer',
    'everysizecheckout_export'                => 'Reports',
    'everysizecheckout_shipping_statuses'     => 'Status “shipped”',
    'everysizecheckout_cancellation_statuses' => 'Status “cancelled”',
    'everysizecheckout_return_statuses'       => 'Status “return” (optional)',
    'everysizecheckout_return_statuses_hint'  => 'Leave empty if returns should be treated like cancellations.',
    'everysizecheckout_carrier'               => 'Shipping carrier (default)',
    'everysizecheckout_carrier_hint'          => 'Reported when the carrier of the tracking code cannot be mapped.',
    'everysizecheckout_refund'                => 'Refund',
    'everysizecheckout_auto_refund'           => 'Automatic refund on cancellation before shipping',
    'everysizecheckout_auto_refund_hint'      => 'Automatically refunds the full amount when an order is cancelled before shipping. When switched on, the automation applies from now — older cancellations are never refunded automatically. A Gambio cancellation does not move any money at everysize; money only moves via this automation or the refund button in the order.',
    'everysizecheckout_sync'                  => 'Synchronisation',
    'everysizecheckout_sync_enabled'          => 'Automatic synchronisation',
    'everysizecheckout_sync_hint'             => 'Runs via the Gambio cronjob (Toolbox → Cronjobs, every 5 minutes) and via the command “php GXModules/Everysize/Checkout/bin/sync.php”. Imports orders and reports shipping, cancellation and returns.',
    'everysizecheckout_save'                  => 'Save',
];
