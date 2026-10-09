# Abnahme-Checkliste WorkwearCenter 10.12.0

Diese Liste nach dem Upload im Wartungsmodus vollständig durchgehen. Erst danach öffentlich freischalten.

## Sicherung und Update

- [ ] Webspace, Datenbank und `config.php` separat gesichert.
- [ ] Versteckte Dateien `.htaccess`, `.user.ini` und `uploads/.htaccess` übertragen.
- [ ] `/update.php` als Administrator erfolgreich ausgeführt.
- [ ] Admin → Systemstatus zeigt das Datenbankschema als erfüllt.
- [ ] Admin → Systemstatus: „Interne Dateien gesperrt“ ist grün (sonst Webserver-Sperren laut START-HIER eintragen).
- [ ] `https://workwearcenter.de/uploads/katalog/daiber.json` liefert keinen Inhalt (403 oder 404).
- [ ] Bestehende Firmen, Produkte, Konten und Bestellungen stichprobenartig vorhanden.
- [ ] 10.11: `/update.php` ein zweites Mal ausgeführt – meldet 0 Datenbankanpassungen; Systemprüfung „Datenbank vollständig“.
- [ ] 10.11: Eine alte Bestellung zeigt im Shop und in der Verwaltung dieselben Beträge wie vor dem Update.

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

## Neu in 10.11

Firmenshop (am Computer und am Handy prüfen)
- [ ] Übersicht: Begrüßung, Budget-Karte, letzte Bestellung, „Nochmal bestellen“.
- [ ] Produkte: Karten mit Farbpunkten; Detailansicht öffnet, Farbe/Größe/Menge wählbar, Adresse mit `?artikel=` lässt sich direkt aufrufen.
- [ ] Testbestellung mit einem 19-%-Artikel (z. B. 3 Stück) und einem 7-%-Artikel bei einer Firma mit Eigenanteil und Versand: Betrag in „Deine Auswahl“ und in der Handy-Leiste = Betrag in „Meine Bestellungen“ = Betrag auf der Auftragsseite.
- [ ] Budgetwarnung erscheint, wenn die Auswahl das Budget übersteigt; Absenden ist dann gesperrt.
- [ ] Anmeldung, Registrierung, „Passwort vergessen“ und „Neues Passwort“ zeigen Firmenfarbe und Logo (bzw. Initialen).
- [ ] Anmeldelink anfordern, Mail kommt an, Anmeldung klappt; zweiter Klick auf denselben Link wird abgelehnt.

Freigaben, Umtausch, Sammelbestellung
- [ ] Freigabe-Mail: „Freigeben“ aus der Mail funktioniert; eigene Bestellung kann nicht selbst freigegeben werden, solange es eine zweite verantwortliche Person gibt.
- [ ] Größentausch beantragen → Verantwortliche/r gibt frei → Verwaltung „Ware erhalten“ → „Erledigt“; Mitarbeiter/in bekommt Mails.
- [ ] Rückgabe: Budget ist erst nach „Erledigt“ wieder frei.
- [ ] Kundenakte → „Umtausch, Jahreswechsel & Sammelbestellung“: Umtauschfrist, Jahreswechsel-Mail und Sammelbestellung gespeichert; Shop zeigt den nächsten Sammeltermin; Übergabe wartet bis zum Stichtag.

Lexware Office und Lohnabzug
- [ ] `lexware_api_key` eingetragen, „Verbindung testen“ grün.
- [ ] Testfirma mit Lexware-Kontakt verknüpft; Rechnungsentwurf für einen Testauftrag erzeugt; Summe in Lexware = Gesamt brutto im WorkwearCenter.
- [ ] Zweiter Versuch für denselben Auftrag wird abgelehnt (keine Doppelrechnung).
- [ ] Lohnabzug-Liste eines Monats (Firmenshop → Auswertung) öffnet sich in Excel mit Umlauten und Komma-Beträgen.

Website und Vertrieb
- [ ] Anfrage mit Logo → Probeshop-Link in der Bestätigungsmail, Probeshop zeigt Logo auf der Kleidung.
- [ ] Verwaltung → Anfragen: Probeshop verlängern und löschen funktioniert.
- [ ] Startseite: Vertrauensbereich (Kundenlogos, Kundenstimme, Ansprechpartner) gepflegt oder bewusst leer.
- [ ] Kundenakte → QR-Code-Aushang gedruckt, QR-Code mit dem Handy getestet.

## Neu in 10.12

Sprachen im Firmenshop (am Computer und am Handy prüfen)
- [ ] `update.php` ausgeführt; Systemstatus ohne Warnung.
- [ ] Anmeldeseite einer Testfirma: Sprachwahl über der Anmeldekarte; Wechsel auf Polnisch und Türkisch funktioniert, die Seite bleibt dieselbe.
- [ ] Browser auf Englisch (oder Handy mit englischer Systemsprache) öffnet den Shop beim ersten Besuch auf Englisch.
- [ ] Nach der Anmeldung: Sprachwahl oben rechts bzw. am Handy neben „Menü“; gewählte Sprache bleibt nach Ab- und Wiederanmelden (auch auf einem anderen Gerät) erhalten.
- [ ] Profil: Sprache ändern und speichern.
- [ ] Testbestellung auf Polnisch bei einer Firma mit Eigenanteil und Versand: Betrag in „Twój wybór“ = Betrag in „Moje zamówienia“ = Betrag auf der Auftragsseite in der Verwaltung.
- [ ] Bestellbestätigung kommt auf Polnisch; Freigabe-Mail an eine Verantwortliche mit eingestellter türkischer Sprache kommt auf Türkisch, die Bestätigungsseite aus der Mail ist türkisch.
- [ ] Passwort-Link und Anmeldelink kommen in der Sprache der Person.
- [ ] Kundenakte → „Umtausch, Jahreswechsel, Sammelbestellung & Sprachen“: nur Deutsch und Polnisch anhaken → Shop bietet nur diese beiden an; nur Deutsch → keine Sprachwahl.
- [ ] Website, Mustershop, Verwaltung und CSV-Exporte bleiben deutsch.
- [ ] Übersetzungen der Sprachen, die eingesetzt werden sollen, von einer Person mit Muttersprache gegengelesen.

Kleine Verbesserungen
- [ ] „Deine Auswahl“: je Position Netto-Stückpreis und Positionsbetrag stimmen mit der Summe überein.
- [ ] Artikel ohne Logo: kein Hinweis „Vorschau mit deinem Firmenlogo“.
- [ ] Mustershop: Begrüßung passt zur Tageszeit.
- [ ] Startseite: neue Shop-Bilder (Musterbau GmbH) am Computer und am Handy.

## Mail und Betrieb

- [ ] Admin → Systemstatus erkennt die SMTP-Konfiguration ohne Warnung.
- [ ] Testmail kommt mit Logo, korrektem Absender und Antwortadresse an.
- [ ] Cronjob für `cron.php` alle fünf Minuten eingerichtet; Systemstatus zeigt „Cronjob läuft“.
- [ ] Fehlgeschlagene E-Mail testweise erneut verarbeitet.
- [ ] HTTPS aktiv; Browser zeigt keine Mixed-Content-Warnung.
- [ ] Wartungsmodus erst nach allen Prüfungen deaktiviert.

## Bewusste Grenzen bestätigt

- [ ] L-Shop bestellt noch nicht automatisch; Beschaffungs-CSV ist der freigegebene Weg.
- [ ] Lexware: Es entstehen nur Rechnungsentwürfe; Finalisieren, Versand und Lieferschein bleiben in Lexware.
- [ ] Kein Textil-Wareneingang wird erwartet.
- [ ] Motivbestand wird manuell korrigiert und nicht automatisch abgebucht.

Abgenommen am: ____________________  Durch: ____________________
