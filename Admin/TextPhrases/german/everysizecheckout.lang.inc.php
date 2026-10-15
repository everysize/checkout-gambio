<?php
/* --------------------------------------------------------------
   everysizecheckout.lang.inc.php (german)
   Sektion "everysizecheckout": Bestell-Detail-Box und Protokoll-Seite.
   Alle Schlüssel sind everysizecheckout_-präfixt, damit Gambio sie
   nicht selbst übersetzt.
   --------------------------------------------------------------
*/

$t_language_text_section_content_array = [
    // Box-Abschnitte
    'everysizecheckout_section_order'   => 'everysize-Bestellung',
    'everysizecheckout_section_refund'  => 'Erstattung über everysize',
    'everysizecheckout_section_reports' => 'Rückmeldungen an everysize',

    // Infotabelle
    'everysizecheckout_es_order_number'    => 'everysize-Bestellnummer',
    'everysizecheckout_imported'           => 'Importiert',
    'everysizecheckout_number_reported'    => 'Bestellnummer an everysize gemeldet',
    'everysizecheckout_not_yet'            => 'noch nicht',
    'everysizecheckout_paypal_capture'     => 'PayPal-Transaktion (Capture-ID)',
    'everysizecheckout_original_email'     => 'Original-E-Mail der Kundin',
    'everysizecheckout_original_email_hint' => '(nur zur Information – Mails des Shops gehen an die everysize-Weiterleitungsadresse der Bestellung)',

    // Erstattung
    'everysizecheckout_paid'            => 'Bezahlt',
    'everysizecheckout_refunded'        => 'erstattet',
    'everysizecheckout_open'            => 'noch offen',
    'everysizecheckout_date'            => 'Datum',
    'everysizecheckout_refund_amount'   => 'Erstatteter Betrag',
    'everysizecheckout_refund_status'   => 'Status',
    'everysizecheckout_refund_id'       => 'Erstattungs-ID',
    'everysizecheckout_amount'          => 'Betrag (EUR)',
    'everysizecheckout_refund_button'   => 'Erstatten',
    'everysizecheckout_refund_confirm'  => 'Erstattung von %s über everysize an die Kundin? Das kann nicht zurückgenommen werden.',
    'everysizecheckout_hint_refund'     => 'Die Erstattung läuft über everysize: der Betrag geht über das PayPal-Konto zurück, mit dem die Kundin bezahlt hat. Kein PayPal-Zugang im Shop.',
    'everysizecheckout_hint_autorefund' => 'Ein Storno der Bestellung vor dem Versand erstattet das Modul automatisch voll (Einstellung unter Module Center → everysize Checkout), solange noch nichts erstattet wurde.',
    'everysizecheckout_refunds_error'   => 'Erstattungen konnten nicht geladen werden:',
    'everysizecheckout_refund_ok'       => 'Erstattung über everysize veranlasst.',
    'everysizecheckout_refund_failed'   => 'Erstattung fehlgeschlagen:',
    'everysizecheckout_refund_invalid'  => 'Bitte einen gültigen Betrag größer 0 angeben.',

    // Rückmeldungen
    'everysizecheckout_report_none'      => 'Bisher keine Rückmeldungen.',
    'everysizecheckout_report_time'      => 'Zeit',
    'everysizecheckout_report_kind'      => 'Meldung',
    'everysizecheckout_report_item'      => 'Artikel / Sendungsnummer / Erstattung',
    'everysizecheckout_report_reason'    => 'Grund / Betrag',
    'everysizecheckout_report_result'    => 'Ergebnis',
    'everysizecheckout_kind_fulfillment' => 'Versand',
    'everysizecheckout_kind_tracking'    => 'Sendungsnummer',
    'everysizecheckout_kind_revocation'  => 'Storno',
    'everysizecheckout_kind_return'      => 'Retoure',
    'everysizecheckout_kind_refund'      => 'Rückerstattung',
    'everysizecheckout_result_sent'      => 'gemeldet',
    'everysizecheckout_result_known'     => 'bekannt',
    'everysizecheckout_result_skipped'   => 'übersprungen',
    'everysizecheckout_auto_refund_sku'  => 'automatisch, Storno vor Versand',

    // Protokoll-Seite
    'everysizecheckout_protocol_title'   => 'everysize Protokoll',
    'everysizecheckout_protocol_empty'   => 'Noch keine Einträge.',
    'everysizecheckout_protocol_time'    => 'Zeit',
    'everysizecheckout_protocol_title_col' => 'Meldung',
    'everysizecheckout_protocol_message' => 'Nachricht',
    'everysizecheckout_protocol_state'   => 'Ergebnis',

    // Bestellübersicht (Spalte über OrdersOverviewColumns-Overload)
    'everysizecheckout_overview_column'  => 'everysize-Nummer',

    // Protokoll-Seite: Filter und Pagination
    'everysizecheckout_protocol_filter_all'   => 'Alle',
    'everysizecheckout_protocol_filter_ok'    => 'OK',
    'everysizecheckout_protocol_filter_error' => 'Fehler',
    'everysizecheckout_protocol_pager'        => 'Seite %1$d von %2$d',

    // Einstellungsseite (Duplikat der module_center_module-Sektion, die der
    // Admin-Kontext hier nicht automatisch mitliefert)
    'everysizecheckout_title'                 => 'everysize Checkout',
    'everysizecheckout_saved'                 => 'Einstellungen gespeichert.',
    'everysizecheckout_credentials'           => 'Zugangsdaten',
    'everysizecheckout_client_id'             => 'Client-ID',
    'everysizecheckout_client_secret'         => 'Client-Secret',
    'everysizecheckout_secret_stored'         => 'gespeichert (zur Änderung neu eintragen; leer lassen zum Behalten)',
    'everysizecheckout_test_connection'       => 'Verbindung testen',
    'everysizecheckout_testing'               => 'Teste Verbindung …',
    'everysizecheckout_test_ok'               => 'Verbindung OK. Shop-ID:',
    'everysizecheckout_test_failed'           => 'Verbindung fehlgeschlagen:',
    'everysizecheckout_no_credentials'        => 'Keine Zugangsdaten vorhanden (bitte eintragen und speichern).',
    'everysizecheckout_remove_credentials'    => 'Zugangsdaten entfernen',
    'everysizecheckout_remove_confirm'        => 'Zugangsdaten wirklich entfernen? Bestellungen und Rückmeldungen bleiben erhalten.',
    'everysizecheckout_credentials_removed'   => 'Zugangsdaten entfernt.',
    'everysizecheckout_env_production'        => 'Umgebung: Produktion.',
    'everysizecheckout_env_test'              => 'Umgebung: Testumgebung.',
    'everysizecheckout_env_invalid'           => 'Die API-URL ist ungültig, das Modul ruft die API nicht auf:',
    'everysizecheckout_import'                => 'Import',
    'everysizecheckout_import_status'         => 'Bestellstatus für neue Bestellungen',
    'everysizecheckout_send_confirmation'     => 'Bestellbestätigung an den Kunden senden',
    'everysizecheckout_export'                => 'Rückmeldungen',
    'everysizecheckout_shipping_statuses'     => 'Status „versendet“',
    'everysizecheckout_cancellation_statuses' => 'Status „storniert“',
    'everysizecheckout_return_statuses'       => 'Status „Retoure“ (optional)',
    'everysizecheckout_return_statuses_hint'  => 'Nicht wählen, wenn Retouren wie Stornos behandelt werden sollen.',
    'everysizecheckout_carrier'               => 'Versanddienstleister (Default)',
    'everysizecheckout_carrier_hint'          => 'Wird gemeldet, wenn der Versanddienstleister der Sendungsnummer nicht zugeordnet werden kann.',
    'everysizecheckout_refund'                => 'Erstattung',
    'everysizecheckout_auto_refund'           => 'Auto-Erstattung bei Storno vor Versand',
    'everysizecheckout_auto_refund_hint'      => 'Erstattet bei einem Storno vor dem Versand automatisch den vollen Betrag. Beim Einschalten gilt die Automatik ab jetzt — ältere Stornos werden nie automatisch erstattet. Eine Gambio-Stornierung bewegt bei everysize kein Geld; Geld geht nur über diese Automatik oder den Erstattungs-Button in der Bestellung.',
    'everysizecheckout_sync'                  => 'Abgleich',
    'everysizecheckout_sync_enabled'          => 'Automatischer Abgleich',
    'everysizecheckout_sync_hint'             => 'Läuft über den Gambio-Cronjob (Toolbox → Cronjobs, alle 5 Minuten) und über den Befehl „php GXModules/Everysize/Checkout/bin/sync.php“. Bestellungen werden importiert und Versand, Storno und Retoure zurückgemeldet.',
    'everysizecheckout_save'                  => 'Speichern',
];
