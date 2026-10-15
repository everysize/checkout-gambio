# everysize Checkout für Gambio (GXModule 1.0.1)

Bindet einen Gambio-Shop an den everysize-Direktkauf an: Bestellungen aus
everysize werden importiert (inkl. Gastkonten, Varianten, Bestandsabzug und
optionaler Bestellbestätigung), und Bestellnummer, Versand, Sendungsnummern,
Storno, Retoure und Auto-Erstattungen werden zurückgemeldet.

Composer-Paket `everysize/checkout-gambio` (Quellcode und Versionen), Modul-Ordner
`GXModules/Everysize/Checkout`.

## Voraussetzungen

- Gambio GX 26.09 (getestet mit 26.09.0)
- PHP ≥ 8.1 mit `curl` und `json`
- Gambio-Cronjob (Toolbox → Cronjobs) — der Abgleich hängt daran

Kein PayPal-Modul nötig: bezahlt wird bei everysize. Erstattet wird über
everysize (das Geld geht über das PayPal-Konto zurück, mit dem bezahlt wurde).

## Installation

Gambio 26.09 hat keinen Installer, der ZIPs entgegennimmt — das Modul wird als
Dateisatz unter `GXModules/Everysize/Checkout` installiert:

1. ZIP (`everysize-checkout-gambio-<version>.zip`, Download in everysize Business
   unter „Shop-Integration“ → Gambio) entpacken und den enthaltenen Ordner
   `GXModules` in das **Shop-Root** kopieren (per FTP/SFTP), so dass
   `GXModules/Everysize/Checkout/GXModule.json` liegt.
2. **Cache leeren**: Admin → Toolbox → Cache → „Cache leeren“.
3. **Module Center**: `admin.php?do=ModuleCenter` → „everysize Checkout" → Installieren.
   Das legt die Modul-Tabellen an (`everysize_checkout_*`, idempotent),
   setzt die Schema-Version und aktiviert den Cronjob im Zusammenspiel mit
   dem Modul-Schalter „Abgleich aktiv" (ein Schalter, kein zweiter Haken).
4. Client-ID und Client-Secret (aus everysize Business, „Shop-Integration") eintragen
   und speichern.
5. Konfiguration: Versand-/Storno-/Retoure-Status, Import-Status,
   Bestellbestätigung an/aus, „Abgleich aktiv".

**Update:** Neue ZIP-Dateien entpacken und den Ordner `GXModules/Everysize/Checkout`
über den bestehenden kopieren (Dateien werden ersetzt, Zugangsdaten und Einstellungen
bleiben in der Datenbank), Cache leeren und dann im Module Center das Modul öffnen —
steht eine neue Schema-Version an, bietet das Module Center dort die Update-Aktion an
(hebt die Tabellen idempotent an; das Modul bleibt installiert und aktiv). Danach einmal
„Toolbox → Cache“ im Admin aufrufen, damit die Sprachtexte des Moduls neu eingelesen
werden (der Text-Cache liegt in der Datenbank, nicht im Dateisystem).

## Cronjob (Import + Rückmeldung)

Der Abgleich läuft über den Gambio-Cronjob (alle 5 Minuten standardmäßig):

- **Admin → Toolbox → Cronjobs**: Dort steht der Task „everysize
  Checkout-Abgleich" mit dem Schalter „aktivieren" (folgt dem Modul-Schalter
  „Abgleich aktiv") und dem Intervall. Die **Cronjob-URL** zum Eintragen in
  einen externen Cron ist dieselbe, die
  Gambio für jeden Cronjob anbietet:

  ```
  {SHOP_URL}/shop.php?do=Cronjobs/Run&token={TOKEN}
  ```

  Der Token steht im Gambio-Admin unter Toolbox → Cronjobs („Cronjob-URL"
  pro Eintrag); er wird vom Shop verwaltet und ist kein Modul-Secret.
- Alternativ per CLI: `php GXModules/Everysize/Checkout/bin/sync.php`
  (`--json` für Maschinen, `--force` zum Umgehen des „Abgleich aktiv"-
  Schalters, Exit-Code 1 bei Fehlern). Die CLI importiert ohne
  Bestellbestätigung — die holt der Cronjob mit dem nächsten Lauf nach
  (`confirmation_sent_at` bleibt dann zunächst NULL).

## Was der Import anlegt

- `orders`-Zeile wie der Gambio-Checkout (Namensspalten `customers_`,
  `delivery_`, `billing_` aus Vor-/Nachname, `orders_hash`, dynamische
  Bestellsprache und `address_format_id`, Gastbestellung ohne Konto).
- `orders_products` mit Brutto-Preisen aus everysize; Varianten erhalten
  `products_model` nach `APPEND_PROPERTIES_MODEL` (Master-Modell + Combi-
  Modell), `properties_combi_model` aus den Werte-Namen und eine
  `orders_products_properties`-Zeile je Eigenschaft (nicht
  `orders_products_attributes` — Eigenschaften sind keine Attribute).
- `orders_total` mit den Titeln und Sortierungen der `ot_*`-Module
  (`MODULE_ORDER_TOTAL_*_SORT_ORDER`, Steuerzeile aus
  `TAX_ADD_TAX` + `tax_rates.tax_description`, Summe fett).
- Bestandsabzug nach der Gambio-Matrix (`STOCK_LIMITED`,
  `products.use_properties_combis_quantity`: 0 = Artikel (+ Combi nur bei
  Attribut-Bestandsprüfung), 1 = nur Artikel, 2 = nur Combi, 3 = nichts),
  symmetrisch zu `xtc_remove_order`. Negativer Bestand ist eine Warnung,
  kein Fehler — die Bestellung ist bezahlt.
- Eine REVOKED-Bestellung wird direkt in den konfigurierten Storno-Status
  gestellt und bucht keinen Bestand ab.

## Rückmeldung an everysize

- Versand (Status-Wechsel) + Sendungsnummern (`orders_parcel_tracking_codes`)
- Storno je Position (Gambio-Storno/Retoure), Auto-Erstattung bei aktivem
  Schalter, Nachmeldung von Sendungsnummern bis 14 Tage
- Jede Rückmeldung wird idempotent vermerkt (`everysize_checkout_report`)
- Erstattungen (Box und automatisch) stehen als Kommentar im Bestellverlauf,
  ohne Statuswechsel; Erstattungen aus der Box zusätzlich im Protokoll
- Der everysize-Import schreibt die Händler-Bestellnummer NACH dem Commit
  zurück; eine fehlgeschlagene Rückmeldung wird beim nächsten Lauf geholt.

## Architektur

- `src/` — Framework-freier Kern (Settings, API-Client, Sync,
  Import/Export-Logik, Unit-getestet).
- `Admin/Classes/` — Gambio-Anbindung (Db, Settings über den
  Konfigurationsspeicher, OrderImporter/OrderMailer, Protocol,
  Cronjob-Task/Dependencies/Logger, Module-Center-Modul).
- `Admin/TextPhrases/` — Sprachdateien (DE/EN).
- `bin/sync.php` — CLI-Einstieg.

## Lizenz

MIT, siehe `LICENSE`.
