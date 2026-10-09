# Abnahme-Checkliste WorkwearCenter 10.10.0

Diese Liste nach dem Upload im Wartungsmodus vollständig durchgehen. Erst danach öffentlich freischalten.

## Sicherung und Update

- [ ] Webspace, Datenbank und `config.php` separat gesichert.
- [ ] Versteckte Dateien `.htaccess`, `.user.ini` und `uploads/.htaccess` übertragen.
- [ ] `/update.php` als Administrator erfolgreich ausgeführt.
- [ ] Admin → Systemstatus zeigt das Datenbankschema als erfüllt.
- [ ] Admin → Systemstatus: „Interne Dateien gesperrt“ ist grün (sonst Webserver-Sperren laut START-HIER eintragen).
- [ ] `https://workwearcenter.de/uploads/katalog/daiber.json` liefert keinen Inhalt (403 oder 404).
- [ ] Bestehende Firmen, Produkte, Konten und Bestellungen stichprobenartig vorhanden.

## Admin-Sicherheit (neu in 10)

- [ ] Zwei-Faktor-Anmeldung eingerichtet, Anmeldung mit Code getestet.
- [ ] Wiederherstellungscodes ausgedruckt bzw. im Passwort-Manager gespeichert.
- [ ] Jede Person mit Admin-Zugang hat ein eigenes Konto (Mein Konto → Administratoren).
- [ ] Nicht mehr benötigte Admin-Konten deaktiviert.

## Übergabe an die Auftragsverwaltung (neu in 10)

- [ ] `handover_*` in der config.php eingetragen (sobald die Auftragsverwaltung bereit ist).
- [ ] Admin → Übergabe → „Verbindung testen“ erfolgreich.
- [ ] Testbestellung freigegeben → erscheint in der Auftragsverwaltung, Referenznummer kommt zurück.
- [ ] Ausfall simuliert (Auftragsverwaltung kurz nicht erreichbar) → Auftrag wird automatisch nachgeliefert.
- [ ] Ältere freigegebene Aufträge bei Bedarf „für Übergabe vorbereitet“.

## Öffentliche Website

- [ ] Startseite auf Desktop und Smartphone ohne Verzerrung oder übergroße Bildfläche.
- [ ] Logo, Fotos und drei echte Mustershop-Produkte werden geladen.
- [ ] Leistungen, Über uns, Ablauf, Kontakt und Anfrageformular erreichbar.
- [ ] Anfrage wird gespeichert, erscheint im Admin und löst eine E-Mail aus.
- [ ] Impressum, Telefonnummer, Anschrift, E-Mail und USt-IdNr. sind korrekt.
- [ ] Rechtlich geprüfter Datenschutztext ist vollständig hinterlegt.

## Firmen-Shop

- [ ] Vorhandener Mitarbeiter kann sich anmelden und nur seine Firma sehen.
- [ ] Registrierung nach konfiguriertem Modus getestet.
- [ ] Verantwortlicher kann neue Registrierung direkt freigeben und ablehnen.
- [ ] Passwort-vergessen-Link kommt an und ist nach Nutzung ungültig.
- [ ] Produktbilder, Größen, Farben, Veredelung und Preise stimmen.
- [ ] Budget und Stücklimit werden sichtbar und serverseitig abgelehnt, wenn überschritten.
- [ ] Testbestellung erscheint in „Meine Bestellungen“ und bei den Freigaben.
- [ ] Ablehnung verlangt einen Grund; Freigabe und Ablehnung erzeugen die richtige Mail.
- [ ] Mitarbeiter-CSV einmal mit zwei Testkonten importiert und wieder exportiert.
- [ ] Letzter Verantwortlicher kann nicht versehentlich deaktiviert werden.
- [ ] Mitarbeiter kann eine offene Bestellung zurückziehen; Budget ist danach wieder frei.
- [ ] Testkonto über „Daten löschen (DSGVO)“ anonymisiert; Bestellung bleibt sichtbar.
- [ ] Neue Firma: Einrichtungs-Checkliste zeigt alle Schritte als erledigt.

## Admin, Produkte und Produktion

- [ ] Produktstamm exportiert und unverändert wieder importiert.
- [ ] Neue SKU angelegt; doppelte SKU wird abgelehnt bzw. im Systemstatus gemeldet.
- [ ] Firmenkollektion, Produktvorschau und Veredelungsposition gespeichert.
- [ ] Veredelungsmotiv verknüpft; Bestand/Bedarf in Produktion plausibel.
- [ ] Beschaffungs-CSV enthält Lieferant, SKU, Farbe, Größe und Menge.
- [ ] Produktionsnotiz gespeichert und Statusfolge getestet.
- [ ] Unveränderter Status verschickt keine zweite Statusmail.
- [ ] Abgeschlossene Aufträge verschwinden aus der Standardansicht „offene Produktion“.
- [ ] Arbeits-CSV enthält Netto, Brutto, Firmen- und Mitarbeiteranteil.

## Neu in 10.3

- [ ] update.php ausgeführt, Systemstatus ohne Warnung.
- [ ] Admin → Inhalte → „Neue Texte übernehmen“ geklickt, Startseite und Unterseiten kontrolliert.
- [ ] Mitarbeiter-Konto: „Nochmal bestellen“ übernimmt Artikel und Größen.
- [ ] Erste Bestellung: Hinweis auf der Übersicht erscheint, Grundausstattung ist vorausgewählt (bei Profil mit Starter-Mengen).
- [ ] Verantwortlicher: Auswertung sichtbar, Excel-Datei öffnet sich mit korrekten Umlauten und Beträgen.
- [ ] Auftragszentrale meldet einen Status: Fortschrittsbalken und ggf. Sendungsnummer erscheinen beim Mitarbeiter, E-Mail kommt an.
- [ ] https://www.workwearcenter.de/sitemap.xml zeigt die richtige Adresse.

## Mail und Betrieb

- [ ] Admin → Systemstatus erkennt die SMTP-Konfiguration ohne Warnung.
- [ ] Testmail kommt mit Logo, korrektem Absender und Antwortadresse an.
- [ ] Cronjob für `cron.php` alle fünf Minuten eingerichtet; Systemstatus zeigt „Cronjob läuft“.
- [ ] Fehlgeschlagene E-Mail testweise erneut verarbeitet.
- [ ] HTTPS aktiv; Browser zeigt keine Mixed-Content-Warnung.
- [ ] Wartungsmodus erst nach allen Prüfungen deaktiviert.

## Bewusste Grenzen bestätigt

- [ ] L-Shop bestellt noch nicht automatisch; Beschaffungs-CSV ist der freigegebene Weg.
- [ ] Lexware-Rechnung und -Lieferschein bleiben manuell.
- [ ] Kein Textil-Wareneingang wird erwartet.
- [ ] Motivbestand wird manuell korrigiert und nicht automatisch abgebucht.

Abgenommen am: ____________________  Durch: ____________________
