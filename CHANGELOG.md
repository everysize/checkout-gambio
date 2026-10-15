# 1.0.1
- The shop version is reported to everysize as the bare version number (e.g. 26.09.0), like the
  other everysize plugins, instead of "Gambio v26.09.0"

# 1.0.0
- Initial release: import of everysize orders into Gambio (guest accounts, variants via
  properties, stock deduction following the Gambio matrix, dynamic order language, address
  formats, REVOKED orders straight into the configured cancellation status) and reporting back
  to everysize: merchant order number, shipping with tracking code, cancellations, returns and
  automatic refunds
- Order box "everysize" in the admin order page: the everysize order as an info table, refunds
  through everysize with tiles (paid / refunded / open), refund list and labelled form, reports
  to everysize as a table; times in the shop's time zone; every refund (box or automatic) is noted
  in the order history (no status change) and, from the box, in the protocol
- Protocol page in the Module Center (last 200 entries)
- Configuration in the Module Center: shipping/cancellation/return statuses, import status,
  order confirmation on/off, one "automatic sync" switch that also drives the cronjob
- Sync via the Gambio cronjob (*/5 minutes by default) or CLI (`bin/sync.php`, `--json`,
  `--force`, exit code 1 on errors); the CLI imports without order confirmation — the cronjob
  sends pending confirmations with the next run
- Order confirmation follows gm_send_order.php: OrderMailBuilder, attachments (terms, privacy
  notice, withdrawal) per configuration, subject per order language, gm_send_order_status as
  double-send protection plus the module's own stamp
- Schema updates are idempotent and versioned (schema 2); uninstalling keeps all data
  (tables, credentials, links) like the other everysize plugins
- Unit tests (101) for the framework-free core under src/, PHP 8.1-8.3
