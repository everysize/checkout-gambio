<?php
/* --------------------------------------------------------------
   module_center_module.everysizecheckout.lang.inc.php (german)
   --------------------------------------------------------------
*/

$t_language_text_section_content_array = [
    'everysizecheckout_title'                => 'everysize Checkout',
    'everysizecheckout_description'          => 'Bindet den Shop an den everysize-Direktkauf an: Bestellungen aus everysize werden importiert; Bestellnummer, Versand, Sendungsnummern, Storno, Retoure und Erstattungen werden zurückgemeldet.',
    'everysizecheckout_saved'                => 'Einstellungen gespeichert.',
    'everysizecheckout_credentials'          => 'Zugangsdaten',
    'everysizecheckout_client_id'            => 'Client-ID',
    'everysizecheckout_client_secret'        => 'Client-Secret',
    'everysizecheckout_secret_stored'        => 'gespeichert (zur Änderung neu eintragen; leer lassen zum Behalten)',
    'everysizecheckout_test_connection'      => 'Verbindung testen',
    'everysizecheckout_testing'              => 'Teste Verbindung …',
    'everysizecheckout_test_ok'              => 'Verbindung OK. Shop-ID:',
    'everysizecheckout_test_failed'          => 'Verbindung fehlgeschlagen:',
    'everysizecheckout_no_credentials'       => 'Keine Zugangsdaten vorhanden (bitte eintragen und speichern).',
    'everysizecheckout_remove_credentials'   => 'Zugangsdaten entfernen',
    'everysizecheckout_remove_confirm'       => 'Zugangsdaten wirklich entfernen? Bestellungen und Rückmeldungen bleiben erhalten.',
    'everysizecheckout_credentials_removed'  => 'Zugangsdaten entfernt.',
    'everysizecheckout_env_production'       => 'Umgebung: Produktion.',
    'everysizecheckout_env_test'             => 'Umgebung: Testumgebung.',
    'everysizecheckout_env_invalid'          => 'Die API-URL ist ungültig, das Modul ruft die API nicht auf:',
    'everysizecheckout_import'               => 'Import',
    'everysizecheckout_import_status'        => 'Bestellstatus für neue Bestellungen',
    'everysizecheckout_send_confirmation'    => 'Bestellbestätigung an den Kunden senden',
    'everysizecheckout_export'               => 'Rückmeldungen',
    'everysizecheckout_shipping_statuses'    => 'Status „versendet“',
    'everysizecheckout_cancellation_statuses'=> 'Status „storniert“',
    'everysizecheckout_return_statuses'      => 'Status „Retoure“ (optional)',
    'everysizecheckout_return_statuses_hint' => 'Nicht wählen, wenn Retouren wie Stornos behandelt werden sollen.',
    'everysizecheckout_carrier'              => 'Versanddienstleister (Default)',
    'everysizecheckout_carrier_hint'         => 'Wird gemeldet, wenn der Versanddienstleister der Sendungsnummer nicht zugeordnet werden kann.',
    'everysizecheckout_refund'               => 'Erstattung',
    'everysizecheckout_auto_refund'          => 'Auto-Erstattung bei Storno vor Versand',
    'everysizecheckout_auto_refund_hint'     => 'Erstattet bei einem Storno vor dem Versand automatisch den vollen Betrag. Beim Einschalten gilt die Automatik ab jetzt — ältere Stornos werden nie automatisch erstattet. Eine Gambio-Stornierung bewegt bei everysize kein Geld; Geld geht nur über diese Automatik oder den Erstattungs-Button in der Bestellung.',
    'everysizecheckout_sync'                 => 'Abgleich',
    'everysizecheckout_sync_enabled'         => 'Automatischer Abgleich',
    'everysizecheckout_sync_hint'            => 'Läuft über den Gambio-Cronjob (Toolbox → Cronjobs, alle 5 Minuten) und über den Befehl „php GXModules/Everysize/Checkout/bin/sync.php“. Bestellungen werden importiert und Versand, Storno und Retoure zurückgemeldet.',
    'everysizecheckout_save'                 => 'Speichern',
];
