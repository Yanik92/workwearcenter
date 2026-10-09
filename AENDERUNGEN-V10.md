# WorkwearCenter 10.0.0 – Änderungen

Stand: 26. September 2026 · enthält alle Korrekturen aus 9.1.0

## Neu in Version 10

### Übergabe an die Auftragsverwaltung
- Jede freigegebene Bestellung erzeugt automatisch einen vollständigen, unveränderlichen Auftragsdatensatz: Firma, Mitarbeiter, Kostenstelle, Lieferadresse, Artikel, Größen, Veredelungspositionen und alle Preise und Anteile.
- **Push:** signierte Übertragung (HMAC-SHA256) an die Auftragsverwaltung, mit 10 automatischen Wiederholungen über ca. 2 Tage. Danach bekommen alle Admins eine E-Mail.
- **Pull:** Abruf-Schnittstelle `/api/v1/auftraege` mit API-Schlüssel und Quittierung.
- Die Auftragsnummer der Auftragsverwaltung wird zurückgemeldet und beim Auftrag angezeigt.
- Admin-Bereich „Übergabe“ mit:
  - Status und Verbindungstest,
  - Protokoll mit Rohdaten,
  - „Erneut senden“,
  - Nachholen älterer Aufträge.
- Übergabestatus in der Auftragsliste; Fehler erscheinen im Dashboard unter „Heute zu erledigen“.
- Die Aufträge werden auch bei ausgeschalteter Schnittstelle gesammelt.
- Vollständige Anleitung mit echtem Beispiel-Datensatz und lauffähigem Beispielempfänger: `SCHNITTSTELLE-AUFTRAGSVERWALTUNG.md`, `tools/beispiel-empfaenger-auftragsverwaltung.php`.

### Admin-Sicherheit
- Zwei-Faktor-Anmeldung (Authenticator-App, QR-Code). Bereits benutzte Codes werden abgelehnt, 10 Wiederherstellungscodes zum Ausdrucken.
- „Mein Konto“: eigenes Passwort ändern (andere Geräte werden abgemeldet).
- Mehrere Admins: einladen per 24-Stunden-Link, deaktivieren (sofortige Abmeldung). Das eigene Konto ist geschützt.
- Das Dashboard erinnert, solange die Zwei-Faktor-Anmeldung fehlt.

### Marktreife und DSGVO
- **Neuinstallation** über `install.php` (geschützt durch `install_key`, sperrt sich nach der Einrichtung selbst).
- **Daten löschen (DSGVO):** Admin und Verantwortliche können ausgeschiedene Mitarbeitende anonymisieren. Bestellungen bleiben für die Aufbewahrungspflichten erhalten.
- Automatische Datensparsamkeit per Cronjob: versendete Mails nach 90 Tagen, erledigte Anfragen nach 24 Monaten.
- Impressum um Umsatzsteuer-ID und Handelsregister erweitert.
- Vorlage für die Datenschutzerklärung, die alle Datenverarbeitungen des Systems beschreibt (zum Prüfen und Anpassen).
- Mitarbeitende können **offene Bestellungen zurückziehen**. Das Budget ist sofort wieder frei, und die Verantwortlichen werden informiert.

### Leichte Bedienung
- Einrichtungs-Checkliste je Firma mit Fortschrittsbalken und „Erledigen“-Knöpfen, die direkt zur richtigen Stelle springen.
- Die Firmenliste zeigt den Einrichtungsstand (z. B. 6/7) und deaktivierte Shops.
- Überarbeitete Anleitung im Admin (Einrichtung und Tagesablauf).

## Aus Version 9.1.0

- E-Mails: RFC-konform kodiert (vorher Zeilen mit bis zu 5.600 Zeichen, erlaubt sind 998).
- Shop-Kurznamen wie `impressum` oder `admin` gesperrt.
- Neue Firmen: Lieferadresse und Registrierungsmodus direkt beim Anlegen.
- Admin kann den ersten Verantwortlichen anlegen; Firmen-Shops lassen sich deaktivieren.
- Datenbank-Zeitzone passt jetzt zu PHP (Budgetjahr-Grenzen und Linkablauf).
- Content-Security-Policy ohne `unsafe-inline` für Skripte.
- Admin-Login zusätzlich pro Konto gegen verteiltes Passwort-Raten gedrosselt.
- Warnungen bei riskanten Shop-Einstellungen.
- Anfragen DSGVO-konform löschbar.
- Optik:
  - Startseiten-Überschrift ohne Wortbruch,
  - Budgetkarte mit Fortschrittsbalken,
  - Mengen-Stepper,
  - Budgetwarnung vor dem Absenden,
  - mobile Bestellleiste,
  - Admin-Seitenleiste reicht bis zum Seitenende.

## Getestet

- Syntax aller PHP-Dateien und `assets/app.js`.
- `tests/security_regression.php`: u. a. RFC-6238-Testvektor, Code-Wiederverwendung, Übergabe-Signatur, HTTPS-Pflicht, reservierte Kurznamen, Mail-Zeilenlänge.
- **Update-Pfad:** Originalschema 9.0.1 → 10.0.0 erfolgreich. Ein zweiter Lauf ändert nichts.
- **Neuinstallation** auf leerer Datenbank: falscher Schlüssel wird abgelehnt, Einrichtung, Selbstsperre, Admin-Anmeldung.
- Browser-Durchlauf Desktop und Smartphone:
  - Bestellung → Freigabe → automatische Übergabe an den Beispielempfänger, Signatur geprüft, Referenz zurückgemeldet.
  - Abruf und Quittierung über die API.
  - Ausfall des Empfängers → wartet und wird erneut gesendet → übergeben.
  - Zwei-Faktor-Einrichtung, Anmeldung, falscher Code, Wiederherstellungscode (nur einmal gültig).
  - Bestellung zurückziehen, anschließende Freigabe wird verhindert.
  - DSGVO-Anonymisierung; das eigene Konto ist geschützt.
  - Impressum mit USt-IdNr.
  - Keine JavaScript-Fehler, keine PHP-Warnungen.

## Bewusst offen

1. **Anbindung an die konkrete Auftragsverwaltung:** Die Schnittstelle ist fertig und getestet. Sobald das Zielsystem bekannt ist, wird die Empfängerseite angepasst.
2. **Rechtstexte** (Datenschutz, ggf. AGB bei Mitarbeiter-Eigenanteil) fachlich prüfen lassen.
3. **Hosting-Seite:** HTTPS, Cronjob, tägliche Datensicherung, Auftragsverarbeitungsvertrag (AVV) mit Hoster und Mailanbieter.
4. Lexware Office und L-Shop weiterhin manuell (wie in Version 9).

## Nachtrag 10.1.0 – Anbindung an die Yanik Auftragszentrale

- Neues Übergabeformat `handover_format => 'auftragszentrale'`. Das WorkwearCenter spricht jetzt direkt das Empfangsprotokoll der Auftragszentrale (`channel.php`, `order.upsert`, X-Yanik-Signatur mit Nonce).
- Freigegebene Bestellungen erscheinen dort als YT-Auftrag mit Artikeln, Größen, Farben, SKU und Veredelung. Mitarbeiter, Kostenstelle, Freigabe, Lieferadresse und Beträge stehen in den Notizen. Die YT-Nummer wird im WorkwearCenter angezeigt.
- „Verbindung testen“ nutzt das Ping-Ereignis der Auftragszentrale. Erneutes Senden erzeugt keine Dubletten.
- Ende-zu-Ende getestet: Bestellung → Freigabe → YT-2026-00002 in der Auftragszentrale → Referenz zurück im WorkwearCenter.

## Nachtrag 10.2.0 – Neue Startseite

- Startseite komplett neu gestaltet:
  - dunkler Einstieg mit einem **echten Bild des Mitarbeiter-Shops** (Desktop und Smartphone) statt eines Stofffotos,
  - Vertrauensleiste,
  - Gegenüberstellung „Bisher / Mit dem WorkwearCenter“,
  - Bausteine, Kollektion mit „Ihr Logo“,
  - Abschnitt „Veredelt in Salzkotten“ mit echten Kundenarbeiten,
  - Ablauf als Zeitstrahl,
  - Fragen als Aufklapp-Liste,
  - Abschluss mit Telefon und E-Mail.
- Alle Texte bleiben unter Admin → Inhalte bearbeitbar (Einstieg, Veredelung und Bausteine, Ablauf, Fragen, Abschluss). Die Bilder unter Admin → Design werden im Abschnitt „Veredelt in Salzkotten“ verwendet.
- Neue Schrift **Inter**, selbst gehostet: Es gibt keine Verbindung zu Google und keinen Hinweis in der Datenschutzerklärung nötig. Sie gilt für die gesamte Website, die Firmenshops und den Admin.
- Neuer **Kunden-Login** (`/zugang`) in der Navigation: Mitarbeitende geben den Kurznamen ihres Firmenshops ein (oder fügen den Link ein) und landen direkt bei ihrer Anmeldung.
- Ein fiktives Demo-Logo („Musterbau“) wird nur für die Beispielbilder verwendet und ist nicht Teil des Pakets.

## Nachtrag 10.3.0 – Texte, Unterseiten, Statusrückmeldung, Nachbestellen, Auswertung, SEO

**Wichtig nach dem Hochladen:** `update.php` ausführen (Datenbankstand 10.3.0). Danach unter Admin → Inhalte auf **„Neue Texte übernehmen“** klicken. Die bisherigen Texte werden dabei gesichert und lassen sich mit „Sicherung zurückholen“ wiederherstellen.

**Texte und Unterseiten**
- Alle Texte der Website wurden neu geschrieben: klar, in der Sie-Form und aus Sicht eines Handwerksbetriebs aus Salzkotten.
- Leistungen, So funktioniert's, Über uns, Kontakt, Anfrage, Impressum und Datenschutz haben das Design der neuen Startseite.

**Statusrückmeldung aus der Auftragszentrale**
- Neue Adresse `POST /api/v1/status` mit derselben Signatur wie die Übergabe. Beschreibung für die Auftragszentrale: `STATUS-RUECKMELDUNG-AN-WORKWEARCENTER.md`.
- Mitarbeitende sehen bei jeder freigegebenen Bestellung einen Fortschrittsbalken (Freigegeben → In Produktion → Fertig → Versendet/Abgeholt).
- Bei Versand erscheinen die Sendungsnummer und der Knopf „Sendung verfolgen“ (DHL, DPD, UPS, GLS, Hermes). Zusätzlich geht eine E-Mail an die Person.
- Gegen Missbrauch geschützt: Signatur, 5-Minuten-Zeitfenster, jede Anfrage nur einmal gültig. Nur freigegebene Bestellungen werden angenommen, und fremde Links werden verworfen.

**Nachbestellen leicht gemacht**
- Knopf „Nochmal bestellen“ bei jeder eigenen Bestellung übernimmt Artikel, Größen und Mengen.
- Der Shop merkt sich die zuletzt bestellte Größe je Artikel.
- Bei der ersten Bestellung gibt es einen Hinweis auf der Übersicht. Ist ein Ausstattungsprofil mit Starter-Mengen hinterlegt, ist die Grundausstattung mit einem Klick vorausgewählt.
- Alle Regeln wie Budget, Limits und Freigabe werden wie immer auf dem Server geprüft.

**Auswertung für Firmenkunden** (nur Verantwortliche, Bereich „Mitarbeiter“)
- Artikelübersicht mit Stückzahl, Firmen- und Mitarbeiteranteil.
- Umschaltbar zwischen aktuellem Budgetjahr und Vorjahr, auch für „Verbrauch nach Organisation“.
- „Für Excel herunterladen“ liefert eine CSV-Datei mit einer Zeile je Bestellposition (plus Versand). Enthalten sind Mitarbeiter, Personalnummer, Abteilung, Kostenstelle, Standort und alle Anteile, direkt filterbar in Excel.

**Suchmaschinen (SEO)**
- Neue Seitentitel und Beschreibungen mit den Suchbegriffen, nach denen Kunden suchen (Firmenkleidung, Arbeitskleidung mit Logo, Stick, Textildruck, Salzkotten, Paderborn).
- Strukturierte Firmendaten (LocalBusiness mit Adresse aus dem Impressum) für Google.
- `sitemap.xml` und `robots.txt` werden jetzt automatisch erzeugt und passen sich an (mit oder ohne www). Alte feste Dateien entfernt `update.php` selbstständig.
- Der Kunden-Login `/zugang` wird nicht mehr in Google aufgenommen. Firmenshops waren schon vorher gesperrt.

## Nachtrag 10.3.1 – Systemprüfung auf der Update-Seite

- `update.php` zeigt unten eine **Systemprüfung**: Programmversion, PHP-Version, ob alle Programmteile laden, ob die Datenbank vollständig ist (mit technischem Grund), Datenbankstände, Wartungsmodus und den letzten schweren PHP-Fehler.
- Schwere PHP-Fehler der Website werden in `app/letzter-fehler.txt` festgehalten. Die Datei ist von außen gesperrt.
- Geänderte Dateien: `index.php`, `update.php`, `app/modules/corporate.php`, `VERSION`.

## Nachtrag 10.3.2 – Reparatur bei abweichenden Tabellen

- `update.php` repariert selbstständig Hilfstabellen, die in älteren Ständen ohne Spalte `id` angelegt wurden. `yw_api_nonces` wird neu angelegt, weil sie nur kurzlebige Einmalwerte enthält. Bei den anderen wird `id` ergänzt, ohne dass Daten verloren gehen.
- Die Systemprüfung nennt jetzt die genaue Tabelle und deren Spalten, falls noch etwas fehlt.

## Nachtrag 10.4.0 – Website neu gestaltet (ruhiger, echter, aufgeräumter)

- Neues Erscheinungsbild für Startseite und alle Unterseiten: warmes Papierweiß, dunkle Schrift, Orange nur für Knöpfe. Große echte Fotos aus der Werkstatt statt Symbolbilder.
- Entfernt wurden typische Baukasten-Elemente: kleine orange Überschriften über jedem Abschnitt, Icon-Kacheln, Häkchen-Listen, schwebende Hinweise, Vorher/Nachher-Kästen, Farbverläufe und gerenderte Produktbilder.
- Startseite in 6 klaren Abschnitten: Einstieg mit Foto, Ablauf mit echtem Shop-Bild, Nutzen für Unternehmen und Mitarbeitende, Werkstatt mit Ansprechpartner, Fragen, Kontakt.
- Telefonnummer direkt im Einstieg, feste Kopfzeile mit „Anfragen“-Knopf, Menü am Handy mit großen Flächen, „Zum Inhalt springen“ für Tastaturnutzer.
- Bilder unter Admin → Design austauschbar: Einstiegsbild, zwei Werkstattbilder, Leistungen, zwei Bilder für „Über uns“.
- Alle Texte bleiben unter Admin → Inhalte pflegbar. Kein Datenbank-Update nötig.

## Nachtrag 10.5.0 – Website überarbeitet: Navyblau, Orange, Weiß

- Neues, klar geordnetes Design in den Firmenfarben Navyblau, Orange und Weiß (ersetzt 10.4).
- Einheitliches Raster: ein Seitenrahmen, linksbündige Abschnittsköpfe, gleiche Karten, gleiche Bildformate.
- Startseite: Einstieg mit Shop-Ansicht, Leistungen als drei Karten, Firmenshop mit Handy-Ansicht, Ablauf in vier Schritten, Werkstatt-Fotos, Fragen, Abschluss.
- Echte Werkstattfotos sauber zugeschnitten (`assets/media/work/`), unruhige Hintergründe entfernt.
- **Keine Telefonnummer mehr auf der Website**, auch nicht im Impressum, in den Suchmaschinen-Daten oder in den Standardtexten. Kontakt läuft über E-Mail und Anfrageformular.
- Kein Datenbank-Update nötig.

## Nachtrag 10.5.1 – Texte der Unterseiten neu geschrieben

- Leistungen, So funktioniert’s, Über uns, Kontakt und Anfrage haben eigene, auf das Design abgestimmte Texte. Doppelte Überschriften und Wiederholungen sind entfernt.
- Diese Texte hängen nicht mehr von alten gespeicherten Inhalten ab. Unter Admin → Inhalte → Unterseiten steht jetzt ein Hinweis.
- Einheitlicher Begriff „Firmenshop“ auf allen Unterseiten.

## Nachtrag 10.6.0 – Produktbilder mit Logo, automatisch

**Wichtig:** `update.php` ausführen (Datenbankstand 10.6.0).

- **Logo-Datei am Motiv:** Unter Firma → „Veredelungsmotive & Druckbestand“ bekommt jedes Motiv eine Logo-Datei (SVG oder PNG mit transparentem Hintergrund). SVG-Dateien werden beim Hochladen entschärft (keine Skripte, keine externen Verweise).
- **Ansichten am Stammprodukt:** Zusätzlich zum Produktbild (vorne) können Rückansicht und Ärmelansicht hochgeladen werden.
- **Druckzonen je Artikel:** Admin → Produkte → „Druckzonen festlegen“. Ansicht und Position wählen, auf das Bild klicken, Breite per Regler. Gilt für alle Firmen. Ohne eigene Zonen gelten sinnvolle Standardzonen.
- **Bild entsteht von selbst:** Sobald bei einem Firmenartikel Position, Verfahren und Motiv eingetragen sind, zeigt der Shop das Produktfoto mit dem Logo an der richtigen Stelle – vorne, hinten und am Ärmel umschaltbar. Stick wirkt leicht erhaben, Druck flach. Je Position gibt es die Größe klein / normal / groß.
- **Korrekturabzug:** In der Firmen-Kollektion zeigt jedes Produkt die Vorschau und einen Knopf „Korrekturabzug als PNG herunterladen“ (alle Ansichten nebeneinander, mit Datum).
- **Hinweise statt Rätselraten:** Fehlt eine Logo-Datei oder eine Ansicht, steht in der Vorschau genau, was fehlt.
- **Eigene Vorschaubilder** einer Firma haben weiterhin Vorrang.
- **Schnittstelle zur Auftragszentrale:** je Veredelung kommen die Felder `motif_file` (Adresse der Logo-Datei) und `size` hinzu. Nur ergänzt, nichts umbenannt.
- Neue Dateien: `app/modules/mockup.php`; Ordner `uploads/motifs/`.

## Nachtrag 10.6.1 – Fußzeile in der Verwaltung

- Die Verwaltung, die Admin-Anmeldung und der Update-Assistent zeigen wieder eine schlichte Fußzeile im Admin-Design. Vorher erschien dort die Fußzeile der Website ohne Gestaltung.

## Nachtrag 10.6.2 – WorkwearCenter-Logo

- Neues Logo „WorkwearCenter“ (ohne ®-Zeichen) in Website, Firmenshop-Login, Verwaltung und Fußzeile. Dateien: `assets/media/workwearcenter-logo.png` (helle Schrift für dunkle Flächen) und `workwearcenter-logo-dunkel.png` (für E-Mails auf weißem Grund).
- Eigenes Browser-Symbol (Favicon) aus der Bildmarke: `assets/media/workwearcenter-icon.png`.
- `update.php` setzt ein früher unter Admin → Design hochgeladenes Logo einmalig zurück, damit das neue Logo erscheint. Ein eigenes Logo kann dort jederzeit wieder hochgeladen werden.
- **`update.php` ausführen** (Datenbankstand 10.6.2).

## Nachtrag 10.6.3 – Systemname „WorkwearCenter“

- Das System heißt überall WorkwearCenter: Seitentitel, Verwaltung, Firmenshop-Login, E-Mails (Absender „WorkwearCenter“, Grußformel „Ihr Team vom WorkwearCenter“), Update-Assistent, Google-Daten. Yanik Textildruck bleibt als Betreiber genannt.
- `update.php` passt die gespeicherten Einstellungen „Seitentitel“ und „Footer-Text“ einmalig an (nur, wenn dort noch „Yanik Workwear“ steht).
- **`update.php` ausführen** (Datenbankstand 10.6.3).

## Nachtrag 10.7.0 – Verwaltung neu geordnet

**`update.php` ausführen** (Datenbankstand 10.7.0).

**Kundenakte.** Ein Klick auf einen Kunden öffnet alles zu dieser Firma mit Reitern: Übersicht · Kollektion & Design · Mitarbeiter · Regeln & Budget · Aufträge · Zugänge & Links. Der Menüpunkt „Ausstattung & Budgets“ ist darin aufgegangen (alte Links leiten weiter). Neu in der Übersicht: Kennzahlen, Einrichtungsstand und ein internes Notizfeld.

**Auftragsseite.** Jede Bestellung hat eine eigene Seite: Positionen mit Veredelung, Beträge, Besteller und Lieferung, Produktionsstand direkt änderbar, Übergabestatus, Sendungsnummer, interne Notiz und Verlauf. Die Auftragsliste verlinkt dorthin.

**Menü von 15 auf 8 Punkte.** Übersicht · Anfragen · Aufträge · Produktion · Kunden · Produkte · Website · System. „Neuer Kunde“ ist ein Knopf in der Kundenliste; Inhalte/Design und Systemstatus/Übergabe/Mein Konto/Aktivitäten/Anleitung sind Unterreiter.

**Suche** in der Kopfzeile: findet Kunden, Mitarbeitende, Aufträge (auch „WWC-000012“), Produkte (Name, SKU, Marke).

**„Shop ansehen“** in der Kundenakte öffnet den Firmenshop so, wie ein Verantwortlicher ihn sieht (Vorschau-Modus mit Hinweisleiste; Bestellen und Speichern sind abgeschaltet).

**Datensicherung** unter System: lädt Datenbank und Uploads als ZIP herunter. Auf der Übersicht erscheint ein Hinweis, wenn die letzte Sicherung älter als 7 Tage ist.

**Erinnerungen an Verantwortliche**: Bestellungen und Registrierungen, die länger als 3 Tage auf Freigabe warten, lösen einmalig eine E-Mail an die Verantwortlichen aus (über cron.php oder automatisch höchstens einmal täglich beim Seitenaufruf).

Nicht verändert: Preise, Budgetprüfung, Freigaben, Übergabe an die Auftragszentrale.

## Nachtrag 10.8.0 – API v1 für Auftragszentrale, Fan-Bande und Partner

**Neue Schnittstelle** `https://workwearcenter.de/api/v1/…` mit eigenen Schlüsseln je System. Beschreibung in `API-DOKUMENTATION.md` (lesbar) und `openapi.yaml` (maschinenlesbar, z. B. für Swagger/Postman).

- **API-Zugänge** (Verwaltung → Einstellungen → System → API-Zugänge): Schlüssel anlegen mit Name, System (Auftragszentrale, Fan-Bande, Partner, Test), Rechten (`orders:read`, `orders:write`, `customers:read`, `products:read`) und optionaler IP-Freigabe. Der Schlüssel wird genau einmal angezeigt und nur als Hash gespeichert; sperren und löschen jederzeit möglich; letzte Nutzung sichtbar.
- **Endpunkte**: `ping`, `kunden` (+Kollektion, Mitarbeiterzahl), `produkte` (mit Bildern je Ansicht, ohne Einkaufspreise), `bestellungen` (Filter nach Freigabe, Produktionsstand, Firma, Zeitpunkt; Blättern), `bestellungen/{id}/status` (Produktionsstand + Sendungsnummer, gleiche Wirkung wie die signierte Rückmeldung), `bestellungen/{id}/quittung` (Eingang bestätigen).
- **Schutz**: nur HTTPS, 600 Anfragen je 10 Minuten und Schlüssel, IP-Sperre nach 20 falschen Schlüsseln, jede Schreibaktion im Verlauf mit `api:<system>` protokolliert.
- **Bestehende Wege bleiben**: Push `order.upsert` an die Auftragszentrale, Abruf mit `handover_api_token` und die signierte Statusrückmeldung funktionieren unverändert.
- **Sicherheitsprüfung** des gesamten Systems durchgeführt (Ergebnis siehe Lieferschreiben); Verwaltung meldet sich nach 2 Stunden ohne Aktivität ab.

Datenbank: neue Tabellen `yw_api_keys`, `yw_api_rate` (werden über `update.php` angelegt).

## Nachtrag 10.8.1 – Korrektur

API-Schlüssel ließen sich auf MySQL 8 nicht anlegen („Speichern fehlgeschlagen“), weil der Spaltenname `system` dort ein reserviertes Wort ist. Behoben; keine Datenbankänderung nötig, einfach Dateien ersetzen (update.php kann trotzdem aufgerufen werden).

## Nachtrag 10.9.0 – Daiber-Katalog und Logo-Platzierung

**Logo-Platzierung korrigiert.** Bei hochformatigen Produktfotos saßen die Logos zu groß und verschoben, weil die Position am Rahmen statt am Foto gemessen wurde. Jetzt liegen die Logo-Ebenen immer exakt auf der sichtbaren Bildfläche – im Shop, in der Kundenakte und in der Druckvorschau. Bestehende Druckzonen bleiben gültig.

**Lieferantenkatalog Daiber** (Verwaltung → Produkte → Lieferantenkatalog):
- Die Daiber-SKU-Liste (Excel aus dem Händlerbereich) ist als Katalog eingebaut: 645 Artikel (James & Nicholson, Myrtle Beach, Daiber) mit Farben, Größen, Beschreibung, Material und Händler-Einkaufspreis.
- Artikel suchen (Nummer oder Name) → Farben und Größen anhaken → Verkaufspreis per Faktor auf den Einkauf (wird gemerkt) oder fest → „Produkte anlegen“. Je Farbe entsteht ein Produkt mit Marke, Kategorie, Beschreibung, Größen, EK und VK. Schon vorhandene Farben werden übersprungen. Übergrößen mit Aufpreis werden angezeigt.
- Produktbilder aus der Daiber Media Cloud werden am Dateinamen erkannt (`JN840_navy.jpg`): per Upload (einzeln oder ZIP) oder per FTP in `uploads/katalog/bilder/`, dann „Bilder zuordnen“. Große Fotos werden automatisch auf 1400 px verkleinert. Die Seite listet, welche Dateien noch fehlen.
- Neue Daiber-Listen können jederzeit hochgeladen werden (Excel wird direkt auf dem Server gelesen, keine Zusatzsoftware).

Keine Datenbankänderung; `update.php` kann trotzdem aufgerufen werden.

## Nachtrag 10.10.0 – Marktreife: Sicherheit, Storno, Datenschutz, Katalog

**`update.php` ausführen** (keine neuen Tabellen; macht doppelte Katalog-Artikelnummern eindeutig). `uploads/.htaccess` unbedingt mit hochladen.

**Sicherheit**
- Die eingelesene Daiber-Liste mit Händler-Einkaufspreisen lag bisher öffentlich abrufbar unter `uploads/katalog/daiber.json`. Sie liegt jetzt im privaten Datenordner (`app/private` oder `private_dir` aus der config.php, am besten außerhalb des Webroots) und wird beim ersten Aufruf automatisch dorthin verschoben.
- `uploads/` liefert keine Listen und Datenexporte mehr aus (JSON, CSV, Excel, ZIP, TXT).
- SVG-Logos: Links mit eigenem Namensraum-Präfix (`x:href="javascript:…"`) wurden nicht entfernt. Behoben.
- Bilder aus ZIP-Uploads im Lieferantenkatalog werden jetzt wie Einzelbilder geprüft.

**Systemstatus: neue Prüfungen**
- „Interne Dateien gesperrt“: prüft von außen, ob Programmordner, Lieferantenpreise und Anleitungen wirklich gesperrt sind (wichtig bei nginx oder abweichender Hoster-Konfiguration).
- „Cronjob läuft“, „Datensicherung aktuell“, „Zwei-Faktor für alle Admins“, „Impressum vollständig“.

**Aufträge**
- Neu: **Auftrag stornieren** auf der Auftragsseite (offene und freigegebene Aufträge, mit Grund). Budget und Stückzahlen sind sofort wieder frei. Besteller und ggf. Verantwortliche bekommen eine E-Mail. Noch nicht gesendete Übergaben werden angehalten; ist der Auftrag schon übergeben, weist die Verwaltung darauf hin, ihn auch in der Auftragszentrale zu stornieren.
- **Vier-Augen-Prinzip:** Verantwortliche können ihre eigenen Bestellungen nicht mehr selbst freigeben, sobald es eine weitere verantwortliche Person gibt. Ist nur eine da, bleibt es erlaubt und wird im Verlauf vermerkt.

**Datenschutz (DSGVO)**
- „Daten löschen“ entfernt Name und E-Mail jetzt auch aus Bestell-Schnappschüssen, Übergabe-Datensätzen und Einladungen und löscht noch nicht versendete Mails an die Person.

**Lieferantenkatalog**
- Jede Farbe bekommt eine eigene Artikelnummer (`JN840-NAVY`). Vorher hatten alle Farben dieselbe, wodurch Produkte nicht mehr gespeichert und die Produkt-CSV nicht wieder eingelesen werden konnten. Bestehende Produkte korrigiert `update.php`.
- Übergrößen mit höherem Einkaufspreis (z. B. 4XL–6XL): Sind sie angehakt, wird der höhere Einkaufspreis für den Verkaufspreis verwendet. Vorher wurden sie zum Normalpreis verkauft.

**Kleinere Korrekturen**
- Kundenakte → Design: Die Farbfelder waren leer, und „Design speichern“ setzte beide Firmenfarben auf Schwarz. Behoben.
- E-Mails über den Hoster-Mailversand setzen den Absender jetzt auch im Umschlag (bessere Zustellbarkeit, SPF/DMARC).
- API-Schlüssel (`Authorization: Bearer …`) werden auch unter PHP-FPM/CGI an PHP weitergereicht.
- Fehlermeldungen in der Verwaltung erscheinen wieder auf der Seite, von der das Formular kam.

## Nachtrag 10.11.0 – Neuer Firmenshop, Umtausch, Lexware Office, Probeshop

**Wichtig: Nach dem Hochladen `/update.php` als Administrator ausführen.** Das Update legt neue Tabellen und Spalten an (Umtausch, Anmeldelinks, Freigabe-Links, Probeshops, Lexware, Positionsbeträge). Bis es gelaufen ist, zeigt der Shop Besuchern „Update erforderlich“ und der Cronjob verschickt nur noch Mails. Ein zweiter Aufruf von `update.php` ändert nichts mehr. Vorher wie immer Webspace und Datenbank sichern.

**Firmenshop im neuen Design (wie der Mustershop)**
- Seitenleiste in der Firmenfarbe mit Logo (ohne Logo: Initialen), persönliche Begrüßung und Budget-Karte.
- Schlanke Produktkarten mit einem Preis: „Dein Anteil“ bzw. „übernimmt deine Firma“. Farben eines Artikels erscheinen als eine Karte mit Farbpunkten.
- Artikel öffnen sich in einer Detailansicht mit Logo-Vorschau, Farbe, Größe, Menge, Material und Pflege. Jeder Artikel hat eine eigene Adresse und kann verlinkt werden.
- „Deine Auswahl“ listet alle gewählten Artikel mit Ändern/Entfernen; am Handy zeigt eine Leiste unten Anzahl und Betrag.
- Anmeldung, Registrierung, „Passwort vergessen“, „Neues Passwort“ und der Anmeldelink sehen jetzt einheitlich aus: Firmenfarbe, großes Logo, gleiche Karte.

**Beträge wie in der Lexware-Rechnung**
- Bisher wurde jeder Artikel einzeln auf Cent gerundet und dann mal Menge genommen. Jetzt rechnet das WorkwearCenter wie Lexware Office: Netto-Einzelpreis × Menge, die Mehrwertsteuer je Steuersatz (19 %, 7 %) auf die Nettosumme. Beispiel: 3 T-Shirts zu 8,40 € netto kosten jetzt 29,99 € statt 30,00 € – genau wie auf der Rechnung.
- Versand ist eine eigene Position und wird zusammen mit der Ware desselben Steuersatzes gerundet. Der Hinweis im Shop nennt deshalb den Netto-Versand und „ca.“ den Bruttobetrag.
- Die Summe, die Mitarbeitende beim Auswählen sehen (Auswahl, Handy-Leiste, Budgetwarnung), ist cent-genau die Summe, die gespeichert wird.
- **Alte Bestellungen bleiben unverändert** – mit ihren bisherigen Beträgen, in Übersicht, Auswertung, Übergabe und Export.

**Lexware Office (optional)**
- Neue Seite Verwaltung → System → Lexware Office: Verbindung testen, Belegtitel und Texte festlegen, Firmen mit einem Lexware-Kontakt verknüpfen oder einen anlegen.
- Auf der Auftragsseite „Rechnungsentwurf erzeugen“, auf der Lexware-Seite Monats-Sammelrechnungen (auf Wunsch je Kostenstelle). Es entstehen nur **Entwürfe**; prüfen und versenden immer in Lexware. Jeder Auftrag kann nur einmal abgerechnet werden.
- Einrichtung (API-Schlüssel in der `config.php`) steht in `START-HIER-V10.txt`.
- **Lohnabzug:** Verantwortliche (Auswertung) und Verwaltung (Kundenakte) laden je Monat eine Liste der Eigenanteile je Person für die Lohnabrechnung herunter.

**Bestellungen, Freigaben und Umtausch**
- **Umtausch & Rückgabe:** Mitarbeitende beantragen bei freigegebenen Bestellungen einen Größentausch oder eine Rückgabe (Standard: 30 Tage, je Firma einstellbar, 0 = aus). Verantwortliche entscheiden in „Freigaben“, die Verwaltung bearbeitet alles unter „Umtausch & Retouren“. Gutschriften und Mehrkosten werden mit dem Budget verrechnet.
- **Freigabe per E-Mail:** Verantwortliche können direkt aus der Freigabe-Mail freigeben oder ablehnen (Link 7 Tage gültig, Vier-Augen-Prinzip gilt weiter).
- **Anmeldelink:** Auf der Anmeldeseite kann man sich einen Link schicken lassen und ohne Passwort anmelden (15 Minuten gültig, nur einmal).
- **Sammelbestellung:** Je Firma einstellbar (monatlich oder alle 14 Tage). Freigegebene Aufträge werden bis zum Stichtag gesammelt und dann gemeinsam an die Auftragszentrale übergeben. Der Shop zeigt den nächsten Termin.
- **Mail zum neuen Budgetjahr:** Zum Start des Budgetjahres bekommen alle aktiven Mitarbeitenden eine Mail mit ihrem neuen Budget (je Firma abschaltbar).
- Einstellungen dafür: Kundenakte → „Umtausch, Jahreswechsel & Sammelbestellung“.

**Website und Vertrieb**
- **Probeshop für Interessenten:** Wer im Anfrageformular sein Logo hochlädt, bekommt automatisch einen persönlichen Vorschau-Shop mit eigenem Logo (30 Tage gültig, danach samt Logo gelöscht). Probeshops lassen sich auch in der Verwaltung bei einer Anfrage erzeugen, verlängern oder löschen.
- **Vertrauensbereich auf der Startseite:** Kundenlogos, eine Kundenstimme und Ihr Ansprechpartner sind unter Website → Inhalte pflegbar.
- **QR-Code-Aushang:** In der Kundenakte gibt es eine druckfertige A4-Seite mit QR-Code zur Registrierung bzw. Anmeldung für Pausenraum oder Werkstatt.

**Verwaltung**
- Verlauf und Aktivitätsprotokoll in Klartext (wer hat was getan), gelöschte Konten ohne Namen und E-Mail.
- Neuer Menüpunkt „Umtausch & Retouren“ (Tagesgeschäft) und „Lexware Office“ (System).

## Nachtrag 10.12.0 – Firmenshop in sechs Sprachen

**Wichtig: Nach dem Hochladen `/update.php` als Administrator ausführen.** Das Update legt drei neue Spalten an (Sprache am Mitarbeiterkonto, angebotene Sprachen je Firma, Sprache je Mail in der Warteschlange). Bis es gelaufen ist, zeigt der Shop „Update erforderlich“. Ein zweiter Aufruf ändert nichts mehr. Vorher wie immer Webspace und Datenbank sichern.

**Mehrsprachiger Firmenshop**
- Mitarbeitende und Verantwortliche können den Firmenshop jetzt auf **Deutsch, Englisch, Polnisch, Türkisch, Russisch und Rumänisch** nutzen. Angesprochen wird überall per „du“ (bzw. der in der Sprache übliche lockere Ton am Arbeitsplatz). Budget, Freigabe, Eigenanteil und Größentausch heißen in jeder Sprache durchgehend gleich.
- Übersetzt ist alles, was Mitarbeitende und Verantwortliche sehen: Anmeldung, Registrierung, Passwort und Anmeldelink, Übersicht, Produkte mit Detailansicht und „Deine Auswahl“, Bestellungen, Umtausch & Rückgabe, Freigaben (auch die Bestätigungsseite aus der Freigabe-Mail), Mitarbeiterverwaltung, Auswertung, Lohnabzug und Profil.
- **Sprachwahl:** oben rechts im Shop (am Handy neben dem Menü-Knopf) und auf der Anmelde- und Registrierungsseite. Die Wahl wird am Konto gespeichert und gilt auch auf anderen Geräten. Vor der Anmeldung merkt sich der Browser die Wahl; beim ersten Besuch richtet sich der Shop nach der Spracheinstellung des Browsers (sonst Deutsch). Im Profil lässt sich die Sprache ebenfalls ändern.
- Beträge und Daten erscheinen im Format der Sprache (z. B. „1 234,56 €“ auf Polnisch, „€1,234.56“ auf Englisch). Abgerechnet wird immer in Euro.
- **E-Mails in der Sprache der Person:** Bestellbestätigung, Entscheidung (freigegeben/abgelehnt), Statusänderungen und Versand, Stornierung, Freigabe-Mail an Verantwortliche, Zugang freigegeben/abgelehnt, Passwort-Link, Anmeldelink, neues Budgetjahr sowie alle Umtausch-Mails. Jede Person bekommt die Mail in ihrer eigenen Sprache – auch wenn eine andere Person die Aktion ausgelöst hat (Beispiel: Verantwortliche auf Türkisch gibt frei → Mitarbeiter bekommt die Mail auf Polnisch).
- **Sprachen je Firma:** In der Kundenakte unter „Umtausch, Jahreswechsel, Sammelbestellung & Sprachen“ legen Sie fest, welche Sprachen der Shop dieser Firma anbietet (Standard: alle). Deutsch ist immer dabei. Bietet eine Firma nur Deutsch an, erscheint keine Sprachwahl.
- **Unverändert Deutsch:** öffentliche Website, Mustershop, Probeshop, Verwaltung, Mails an die Verwaltung und die CSV-Exporte (Spaltenüberschriften bleiben deutsch, damit Excel-Vorlagen weiter passen).
- **Produktnamen und -beschreibungen** werden nicht übersetzt; sie erscheinen so, wie sie gepflegt sind.

**Kleine Verbesserungen**
- „Deine Auswahl“ zeigt je Position jetzt den Netto-Stückpreis und den Positionsbetrag (vorher „je 10,00 € brutto“ neben 29,99 € – das passte nicht zusammen).
- Die Detailansicht zeigt „Vorschau mit deinem Firmenlogo“ nur noch, wenn wirklich ein Logo auf dem Artikel liegt.
- Mustershop und Probeshop begrüßen je nach Tageszeit („Guten Morgen“, „Hallo“, „Guten Abend“) statt immer „Guten Morgen, Max.“
- Neue Bilder vom Firmenshop auf der Startseite (Computer und Handy), mit einer erfundenen Beispielfirma „Musterbau GmbH“ – keine echten Kundendaten.

**Getestet**
- Automatische Prüfungen: alle Sprachen haben dieselben Texte und Platzhalter, Mehrzahl-Formen (Polnisch, Russisch, Rumänisch), Zahlen- und Datumsformat, Sprachwahl nur innerhalb desselben Shops (keine Weiterleitung auf fremde Seiten).
- Im Browser: Sprachwahl vor und nach der Anmeldung, Bestellung auf Polnisch mit Eigenanteil und Versand (Betrag im Browser = gespeicherter Betrag), Mails auf Polnisch/Türkisch/Russisch, Freigabe-Link auf Türkisch, Sprachauswahl je Firma. Desktop und Handy in allen sechs Sprachen ohne abgeschnittene oder überlaufende Texte.
- Alle bisherigen Integrationstests (Bestellungen, Design, Lexware, Probeshop, Oberfläche) laufen weiterhin fehlerfrei.

**Bewusst offen**
- Die Übersetzungen sind sorgfältig erstellt, aber nicht von Muttersprachlern gegengelesen. Bitte vor dem Einsatz bei einer Firma von einer Person prüfen lassen, die die Sprache spricht (Texte stehen gesammelt in `app/lang/<sprache>.php`).
- Website, Verwaltung und Exporte bleiben deutsch (siehe oben).


## Nachtrag 10.12.1 – Lieferantenkatalog wieder richtig dargestellt

**Ursache:** Auf dem Server war `assets/v9.css` nicht aktuell (beim FTP-Upload übersprungen oder alte Fassung). Darin stehen die Gestaltungsregeln des Lieferantenkatalogs; ohne sie stehen Suche, Kennzahlen, Trefferkarten und Farbauswahl ungestaltet untereinander.

- Neue Prüfung im Systemstatus: **„Programmdateien vollständig hochgeladen“**. Vergleicht jede Programmdatei mit der Prüfsummenliste `app/data/dateien.json` aus dem Paket und nennt Dateien, die fehlen oder veraltet sind.
- Sind Design-Dateien (`assets/`) veraltet, zeigt die Verwaltung oben auf jeder Seite einen roten Hinweis mit den betroffenen Dateien.
- CSS- und JS-Dateien werden mit ihrer eigenen Prüfsumme statt der Versionsnummer geladen: Jede geänderte Datei holt sich der Browser sofort neu, auch ohne Strg+F5.
- Für Releases: `php tools/dateiliste-erzeugen.php` erzeugt die Prüfsummenliste.

**Beim Hochladen per FileZilla:** Bei der Frage „Zieldatei existiert bereits“ **„Überschreiben“** wählen und „Immer diese Aktion verwenden“ anhaken – nicht „Überschreiben, falls Quelle neuer“. Sonst bleiben ältere Dateien auf dem Server liegen.

## Nachtrag 10.12.2 – Katalogbilder: Zuordnung repariert
- **Zweifarbige Artikel** (z. B. JN010 black/red) wurden nie erkannt, weil der Schrägstrich im Farbnamen im Dateinamen gesucht wurde. Jetzt passt `JN010_black_red.jpg`, also der Name, den Daiber selbst in der Liste führt.
- Dateinamen werden großzügig erkannt: Groß-/Kleinschreibung, Bindestrich oder Unterstrich, Zusätze wie `_front`, `-back` oder ` (1)`. Der längste passende Anfang gewinnt, `JN010_black_red.jpg` landet also nicht bei black.
- Dateinamen mit Leerzeichen, Klammern oder Umlauten wurden beim Hochladen stillschweigend verworfen. Sie werden jetzt übernommen und sicher umbenannt.
- **Große Auswahl:** Viele Bilder auf einmal überschritten die Upload-Grenze des Servers, und es kam nur „Sicherheitsprüfung fehlgeschlagen“. Jetzt überträgt die Seite die Bilder automatisch in Paketen mit Fortschrittsanzeige. Ist ein Formular trotzdem zu groß, erscheint ein klarer Hinweis mit der Größe.
- Nach dem Upload meldet die Seite, welche Produkte noch ohne Bild sind (mit dem erwarteten Dateinamen) und welche Dateien zu keinem Katalogartikel passen. Die Liste „Fehlende Dateien“ nannte bisher falsche Namen (z. B. `jn840-navy_navy.jpg`).

## Nachtrag 10.12.3 – Kundenakte bleibt im Reiter
- Nach dem Speichern in der Kundenakte (z. B. „Kollektion speichern“ unter „Kollektion & Design“) landete man auf der Übersicht der Firma, bei einem Fehler sogar in der Kundenliste. Jetzt bleibt die Seite im selben Reiter, auch wenn eine Meldung erscheint.
- Umsetzung: Jedes Formular der Kundenakte merkt sich den Reiter (`return_to`). Übernommen wird nur ein Ziel innerhalb der Verwaltung und für dieselbe Firma.

## Nachtrag 10.12.4 – Druckzonen automatisch, Komplett-Test
- **Druckzonen werden automatisch erkannt.** Beim ersten Aufruf eines Produkts misst das System die Umrisse des Produktfotos (Vorder-, Rück- und Ärmelansicht) und setzt Brust links/rechts, Ärmel, Nacken, Rücken oben und Rücken groß passend zur Art des Artikels (Oberteil, Hose, Kopfbedeckung, Tasche/Handtuch). Ein Nachpflegen nach dem Einpflegen ist nicht mehr nötig.
- Wechselt das Foto, werden die Zonen neu berechnet. Von Hand angepasste Zonen bleiben unangetastet; im Zonen-Editor stellt „Wieder automatisch erkennen“ den Automatikstand her.
- Unter „Produkte“ setzt „Druckzonen automatisch erkennen“ alle automatischen Zonen auf einmal; der Cronjob erledigt bis zu 100 Produkte pro Lauf. Ohne erkennbares Foto werden die Zonen eines Geschwisterartikels (andere Farbe) übernommen.
- **Katalogbilder:** Dateien mit `_back` werden automatisch zur Rückansicht, mit `_side` zur Ärmelansicht (z. B. `JN840_navy_back.jpg`).
- **Komplett-Test aller Funktionen** (Shop, Bestellung, Freigabe, Umtausch, Exporte, alle Sprachen, Handyansicht). Behoben:
  - Kundenakte, „Regeln & Budget“: Speichern von Budget, PIN, Mengenlimits und Ausstattungsprofilen endete auf einer leeren Seite.
  - Lieferantenkatalog: Die Preisvorschau ließ Übergrößen-Aufpreise weg und rundete brutto falsch.
  - Firmenshop: „Warenwert netto“ enthielt den Versand und war größer als brutto; unter Artikeln ohne Veredelung stand „none“.
  - Neuer Kunde: Die Prüfung des Shop-Kurznamens im Browser wirkte nicht (auch beim Lexware-Kontaktfeld).
  - Firmenkunden-Cockpit zählte offene Registrierungen als aktive Konten.
  - „Vorschau beenden“ führt zurück in die Kundenakte statt in die Kundenliste.

## 10.13.0 – Weniger Klicks im Alltag
- **Neue Registrierungen auf der Startseite:** Verantwortliche sehen offene Registrierungen direkt auf der Startseite ihres Firmenshops und können sie dort mit „Zugang freigeben“ oder „Ablehnen“ entscheiden (bisher versteckt unter „Mitarbeiter“).
- **Neue Firma:** Nach „Firma anlegen“ öffnet sich direkt der Reiter „Mitarbeiter“, um den ersten Verantwortlichen anzulegen.
- **Eingaben bleiben stehen:** Endet ein Formular mit einer Fehlermeldung (z. B. Shop-Kurzname vergeben, Lieferadresse fehlt), sind alle Eingaben wieder im Formular eingetragen. Passwörter, PINs und Schlüssel werden bewusst nicht gemerkt.

## Nachtrag 10.13.1 – Katalogbilder mit beliebigen Dateinamen
- Bilder werden jetzt auch erkannt, wenn der Dateiname nicht mit `Artikelnummer_Farbe` beginnt: Artikelnummer und Farbe dürfen irgendwo im Namen stehen, auch mit Bindestrich oder Leerzeichen (`Foto JN-840 Navy front.jpg`), mit deutscher Suchfarbe (`JN840 schwarz.jpg`, nur wenn eindeutig) oder zusammengeschrieben (`8001_greyheather.jpg`). Bei Artikeln mit nur einer Farbe reicht die Artikelnummer.
- Was trotzdem nicht erkannt wird, steht unter „Dateien ohne passenden Katalogartikel“ mit einem Eingabefeld je Datei: Artikel und Farbe eintippen oder aus den Produkten ohne Bild wählen, „Zuordnen“ – die Datei wird umbenannt und zugeordnet.
- Zweifarbige Artikel (black/red) wurden intern teils unter einem falschen Schlüssel gesucht; behoben.
