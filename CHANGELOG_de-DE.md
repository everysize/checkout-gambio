# 1.0.1
- Die Shop-Version wird an everysize als reine Versionsnummer gemeldet (z. B. 26.09.0), wie bei
  den anderen everysize-Plugins, statt „Gambio v26.09.0“

# 1.0.0
- Erstveröffentlichung: Import von everysize-Bestellungen in Gambio (Gastkonten, Varianten über
  Eigenschaften, Bestandsabzug nach der Gambio-Matrix, dynamische Bestellsprache,
  Adressformate, REVOKED-Bestellungen direkt in den konfigurierten Storno-Status) und
  Rückmeldung an everysize: Händler-Bestellnummer, Versand mit Sendungsnummer, Storno,
  Retoure und automatische Erstattungen
- Bestell-Box „everysize" in der Admin-Bestellseite: everysize-Bestellung als Infotabelle,
  Erstattung über everysize mit Kacheln (Bezahlt / erstattet / noch offen),
  Erstattungsliste und beschriftetem Formular, Rückmeldungen an everysize als Tabelle;
  Zeiten in der Zeitzone des Shops; jede Erstattung (Box oder automatisch) steht als Kommentar
  im Bestellverlauf (ohne Statuswechsel) und, aus der Box, im Protokoll
- Protokoll-Seite im Module Center (letzte 200 Einträge)
- Konfiguration im Module Center: Versand-/Storno-/Retoure-Status, Import-Status,
  Bestellbestätigung an/aus, ein Schalter „Abgleich aktiv", der auch den Cronjob steuert
- Abgleich über den Gambio-Cronjob (Standard */5 Minuten) oder CLI (`bin/sync.php`, `--json`,
  `--force`, Exit-Code 1 bei Fehlern); die CLI importiert ohne Bestellbestätigung — die
  versendet der Cronjob mit dem nächsten Lauf
- Bestellbestätigung nach gm_send_order.php: OrderMailBuilder, Anhänge (AGB, Datenschutz,
  Widerruf) je Konfiguration, Subject je Bestellsprache, gm_send_order_status als
  Doppelsend-Schutz plus eigene Sperre des Moduls
- Schema-Updates idempotent und versioniert (Schema 2); die Deinstallation behält alle Daten
  (Tabellen, Zugangsdaten, Verknüpfungen) wie bei den anderen everysize-Plugins
- Unit-Tests (101) für den framework-freien Kern unter src/, PHP 8.1–8.3
