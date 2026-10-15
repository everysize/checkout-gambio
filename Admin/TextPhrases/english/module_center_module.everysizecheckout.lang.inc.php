<?php
/* --------------------------------------------------------------
   module_center_module.everysizecheckout.lang.inc.php (english)
   --------------------------------------------------------------
*/

$t_language_text_section_content_array = [
    'everysizecheckout_title'                => 'everysize Checkout',
    'everysizecheckout_description'          => 'Connects the shop to the everysize direct purchase: orders from everysize are imported; order number, shipping, tracking codes, cancellation, return and refunds are reported back.',
    'everysizecheckout_saved'                => 'Settings saved.',
    'everysizecheckout_credentials'          => 'Credentials',
    'everysizecheckout_client_id'            => 'Client ID',
    'everysizecheckout_client_secret'        => 'Client secret',
    'everysizecheckout_secret_stored'        => 'stored (enter a new one to change, leave empty to keep)',
    'everysizecheckout_test_connection'      => 'Test connection',
    'everysizecheckout_testing'              => 'Testing connection …',
    'everysizecheckout_test_ok'              => 'Connection OK. Shop ID:',
    'everysizecheckout_test_failed'          => 'Connection failed:',
    'everysizecheckout_no_credentials'       => 'No credentials stored (please enter and save them).',
    'everysizecheckout_remove_credentials'   => 'Remove credentials',
    'everysizecheckout_remove_confirm'       => 'Really remove the credentials? Orders and reports are kept.',
    'everysizecheckout_credentials_removed'  => 'Credentials removed.',
    'everysizecheckout_env_production'       => 'Environment: production.',
    'everysizecheckout_env_test'             => 'Environment: test environment.',
    'everysizecheckout_env_invalid'          => 'The API URL is invalid, the module will not call the API:',
    'everysizecheckout_import'               => 'Import',
    'everysizecheckout_import_status'        => 'Order status for new orders',
    'everysizecheckout_send_confirmation'    => 'Send order confirmation to the customer',
    'everysizecheckout_export'               => 'Reports',
    'everysizecheckout_shipping_statuses'    => 'Status “shipped”',
    'everysizecheckout_cancellation_statuses'=> 'Status “cancelled”',
    'everysizecheckout_return_statuses'      => 'Status “return” (optional)',
    'everysizecheckout_return_statuses_hint' => 'Leave empty if returns should be treated like cancellations.',
    'everysizecheckout_carrier'              => 'Shipping carrier (default)',
    'everysizecheckout_carrier_hint'         => 'Reported when the carrier of the tracking code cannot be mapped.',
    'everysizecheckout_refund'               => 'Refund',
    'everysizecheckout_auto_refund'          => 'Automatic refund on cancellation before shipping',
    'everysizecheckout_auto_refund_hint'     => 'Automatically refunds the full amount when an order is cancelled before shipping. When switched on, the automation applies from now — older cancellations are never refunded automatically. A Gambio cancellation does not move any money at everysize; money only moves via this automation or the refund button in the order.',
    'everysizecheckout_sync'                 => 'Synchronisation',
    'everysizecheckout_sync_enabled'         => 'Automatic synchronisation',
    'everysizecheckout_sync_hint'            => 'Runs via the Gambio cronjob (Toolbox → Cronjobs, every 5 minutes) and via the command “php GXModules/Everysize/Checkout/bin/sync.php”. Imports orders and reports shipping, cancellation and returns.',
    'everysizecheckout_save'                 => 'Save',
];
