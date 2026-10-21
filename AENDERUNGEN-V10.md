# WorkwearCenter 10 – Änderungen

Stand: 21. Oktober 2026 · enthält alle Korrekturen aus 9.1.0

## 10.25.0 – Musterfirma „Hanseatic Haustechnik“ statt KANTENWERK
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5. Die alten Dateien `assets/media/demo/kantenwerk-logo.svg` und `kantenwerk-mark.svg` werden nicht mehr gebraucht und können gelöscht werden.

- Mustershop: Die Musterfirma heißt jetzt Hanseatic Haustechnik GmbH (Sanitär, Heizung, Klima) und hat ein neues Logo. Auf der Kleidung sitzt das Haus-Zeichen als Stick-Logo.
- Mustershop in Dunkelblau passend zum Logo statt Waldgrün.
- Website: Alle Shop-Ansichten (Start, Artikel, Freigaben, Bestellstatus, Mein Spind, Mitarbeiter, Handy) und das Vorschaubild zum Teilen sind neu aufgenommen, mit Hanseatic Haustechnik statt KANTENWERK. Die Adresse in den Ansichten lautet workwearcenter.de/hanseatic.

## 10.24.0 – Logo sitzt richtig, Schuhe ohne Logo
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5. Die automatisch erkannten Druckzonen werden beim nächsten Aufruf bzw. Cronjob neu berechnet; von Hand gesetzte Zonen bleiben unverändert.

- Schuhe, Stiefel, Sandalen usw. bekommen kein Logo mehr ins Bild und im Mustershop keinen Hinweis „Logo · Brust links“. Wer doch eins will, setzt die Druckzone im Editor von Hand.
- Brust links wird jetzt am Rumpf gemessen statt am ganzen Umriss mit Ärmeln: Bei Jacken, Softshells, Westen und Polos sitzt das Logo nicht mehr am Reißverschluss bzw. an der Knopfleiste, sondern auf der linken Brust (gegenüber der Brusttasche) und etwas höher.
- Hosen: „Bein oben“ liegt in der Mitte des linken Hosenbeins am Oberschenkel statt an der Hüfte neben dem Hosenschlitz.

## 10.23.2 – Mustershop mit deinen Produkten, keine Besuche
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

- Mustershop zeigt jetzt Artikel aus Verwaltung → Sortiment → Produkte statt der drei Beispielartikel: echte Fotos, Namen, Farben, Größen und Bruttopreise. Das KANTENWERK-Logo sitzt in derselben Druckzone wie in den echten Firmenshops (bei Caps vorne, bei Hosen oben am Bein).
- Ohne Auswahl nimmt der Mustershop automatisch bis zu neun aktive Artikel mit Foto, abwechselnd aus den Kategorien. Mit dem neuen Knopf „Im Mustershop zeigen“ an jedem Produkt legst du selbst fest, welche Artikel erscheinen (höchstens 12, in der Reihenfolge des Anklickens).
- Weiße Hintergründe der Lieferantenfotos fügen sich ein (keine grauen Kästen mehr).
- Vorher-nachher-Regler: Logo in der Shop-Vorschau passt jetzt zwischen Reißverschluss und Ärmelnaht.
- Keine Einladungen mehr zum Vorbeikommen: Über uns, Kontakt und Startseite bieten Telefon, E-Mail und Muster per Post an; Kartenlink entfernt.

## 10.23.1 – Korrekturen Mustershop und Werkstattfotos
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

- Mustershop: Hinweisbalken oben hat wieder Innenabstand (Text klebte am Rand), die dunkle Linie an der oberen Kante des Shops ist weg.
- Mustershop und Probeshop: Logo auf T-Shirt, Hoodie und Softshelljacke sitzt jetzt kleiner auf der linken Brust statt groß mitten auf der Brust.
- Vorher-nachher-Regler: Logo in der Shop-Vorschau sitzt an derselben Stelle wie auf der echten Jacke (unter der Brusttasche).
- Stollhans-Jacke korrekt als Druck bezeichnet: „Gedruckt in Salzkotten“, „Echt gedruckt“, Verfahren „Druck“, Alternativtexte angepasst.

## 10.23.0 – Website: Standort Salzkotten, B2B-Positionierung, SEO
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

- Standort überall korrigiert: Firma und Produktion sitzen in der Geseker Straße 19, 33154 Salzkotten. Paderborn erscheint nur noch als Region („Wir betreuen Unternehmen aus Salzkotten, Paderborn und ganz OWL“).
- Startseite neu aufgebaut: H1 „Dein Firmenshop für Arbeitskleidung.“, Unterzeile, fünf Vorteile, Knöpfe „Firmenshop anfragen“ und „Mustershop live testen“ (überall gleich). Danach: das Problem (Excel, WhatsApp, Zettel, falsche Größen und Logo-Positionen) gegen die Lösung, Ablauf in fünf Schritten, neun Funktionen jeweils mit dem Problem, das sie lösen, „Shop und Produktion aus einer Hand“ (Eigenentwicklung, eigene Auftragsverwaltung im Hintergrund, eigene Veredelung), Werkstatt, Branchen, Mustershop mit echten Shop-Ansichten als Reiter, Fallbeispiel, Ansprechpartner, 14 häufige Fragen.
- Alle Website-Texte in „du“, ohne Floskeln. Datenschutzerklärung ebenfalls in „du“.
- Neue Seiten mit eigenem Suchzweck und eigenem Inhalt: /branchen (Handwerk, Industrie, Zeitarbeit, Pflege, Kommunen, Feuerwehr, Dienstleister, Mittelstand), /arbeitskleidung-bestellsystem (Bestellweg, Freigaben, Budgetregeln, Vergleich mit Excel/E-Mail), /mitarbeitershop-arbeitskleidung (Bestellen am Handy, Mein Spind, Zugänge), /workwear-management (Verwaltung, Ein- und Austritte, Auswertungen). Diese Adressen sind als Firmen-Kurzname gesperrt.
- So funktioniert’s, Leistungen und Über uns überarbeitet (Über uns: Problem, Idee, Entwicklung, Heute; Standort Salzkotten).
- Fallbeispiel auf der Startseite (Ausgangssituation, Lösung, Funktionen, Ergebnis). Steht unter Verwaltung → Website & Design → Inhalte → „Startseite – Vertrauen“. Ohne Haken „Kunde hat zugestimmt“ erscheint es anonym („Ein Personaldienstleister aus der Region“).
- Anfrageformular: Kontakt, Telefon (optional), Anzahl Mitarbeiter, Branche, vorhandene Arbeitskleidung, gewünschte Funktionen, Nachricht (optional), Logo und neu eine bestehende Artikelliste (PDF, Excel, CSV, Word oder Bild, max. 4 MB). Die Liste liegt im geschützten Ordner app/private/anfragen, ist nur für Admins herunterladbar (protokolliert) und wird mit der Anfrage gelöscht.
- SEO: Seitentitel und Beschreibungen je Seite, Canonical, Sitemap aus derselben Liste, strukturierte Daten (LocalBusiness mit Anschrift Salzkotten, WebSite, BreadcrumbList, FAQPage, Service) nur mit echten Angaben, Teilen-Bild für Messenger und LinkedIn. Kundenlogin und 404-Seite nicht im Index.
- Bilder: echte Shop-Ansichten und Werkstattfotos als WebP mit Alternativtexten und verzögertem Laden.
- Nicht gefundene Seiten und unbekannte Firmenshops zeigen eine 404-Seite im Website-Design mit Wegen zurück.
- Die tägliche Aufräumroutine (u. a. Anfragen nach 24 Monaten löschen) läuft jetzt auch ohne Cronjob einmal am Tag mit.

## 10.22.0 – Impressum und Datenschutzerklärung DSGVO-konform
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

- Datenschutzerklärung neu: wird jetzt automatisch aus dem tatsächlichen Verhalten des Systems erzeugt und bleibt mit jedem Update aktuell. 14 Abschnitte mit Inhaltsverzeichnis: Verantwortlicher, Hosting und Server-Protokolle, Cookies (Sitzung und Sprachwahl, beide technisch notwendig, kein Cookie-Banner nötig), keine Tracking- oder Fremddienste, Anfrageformular und Probeshop, Geschäftskunden und Rechnungen (Lexware Office nur, wenn eingerichtet), Firmenshops für Mitarbeitende (Arbeitgeber verantwortlich, wir Auftragsverarbeiter nach Art. 28 DSGVO), Empfänger, System-E-Mails, Sicherheitsprotokoll, Speicherfristen als Tabelle, Betroffenenrechte mit hervorgehobenem Widerspruchsrecht und zuständiger Aufsichtsbehörde (LDI NRW).
- Verwaltung → Website & Design → Inhalte: neue Felder für Hoster (vorbelegt: Greenmark IT GmbH / do.de), E-Mail-Anbieter, Speicherfrist der Server-Protokolle, Datenschutzbeauftragten und ergänzende Hinweise. Ein eigener (z. B. anwaltlicher) Text kann weiterhin gewählt werden; der bisher gespeicherte Text bleibt dafür erhalten.
- Impressum: nach § 5 DDG und § 18 Abs. 2 MStV, jetzt mit Telefon, optionaler Wirtschafts-Identifikationsnummer, Hinweis zur Verbraucherstreitbeilegung (ohne die abgeschaffte EU-Schlichtungsplattform), Haftung für Links und Urheberrecht. E-Mail-Adresse ist Pflichtfeld.
- Impressum und Datenschutzerklärung sind auch im Wartungsmodus erreichbar; die Wartungsseite verlinkt beide.
- Löschfristen an die Erklärung angepasst: Anfragen werden 24 Monate nach Eingang gelöscht, unabhängig vom Status (bisher nur erledigte). Protokolleinträge zu Lexware und Lohnabzug werden nach 10 Jahren gelöscht (bisher nie).
- Anfrageformular: Haken heißt jetzt „Ich habe die Datenschutzerklärung gelesen …“ (die Verarbeitung beruht auf der Anfrage, nicht auf einer Einwilligung).
- Systemstatus: Prüfung „Datenschutzerklärung“ erinnert an die Speicherfrist der Server-Protokolle des Hosters.

## 10.21.0 – Firmenshops im neuen Design, „Mein Spind“ und Erfolgsbild
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

Paket 2 von 4 des freigegebenen Konzepts. Die Verwaltung sieht aus wie bisher (kommt mit Paket 3 und 4).
- Firmenshop im Design „Werkstatt-Premium“: Kopfzeile mit Menü als Pillen und Budget-Ring, Begrüßung als große Fläche in Firmenfarbe mit Datum, Abteilung, Budget-Ring und den Knöpfen „Neue Bestellung starten“ und „Mein Spind“, gestrichelte Naht als Abschluss. Gleiche Schriften wie die Website. Firmenfarbe für Flächen und Auswahl, Orange nur für die Hauptaktionen (neue Bestellung, in die Auswahl, absenden).
- Startseite: Karten für die letzte Bestellung (Fortschritt mit Lieferwagen), offene Freigaben und „Mein Spind“; die Kollektion als Karten mit Bild.
- Artikel: Größen als Knöpfe (mit Pfeiltasten bedienbar, Vorschlag aus der letzten Bestellung bleibt), Preis aufgeteilt in „Firma übernimmt“ und „Dein Anteil“ mit Balken.
- Neu „Mein Spind“ (/firma/spind): alle gelieferten Teile mit Größe, Menge und Lieferdatum, abgeschlossener Größentausch und Rückgaben sind eingerechnet. Je Teil „Nachbestellen“ und, solange die Frist läuft, „Größe tauschen“. Freigegebene, noch nicht verschickte Teile stehen unter „Unterwegs zu dir“. Im Menü für alle, in der Handy-Leiste für Mitarbeitende (Verantwortliche über Startseite und Kontomenü).
- Nach dem Absenden: Erfolgsbild mit Konfetti, Bestellnummer, Anzahl Teile, eigenem Anteil und Budget danach. Erscheint einmal, „Status ansehen“ schließt es.
- Handy: schwebende Menüleiste unten, Begrüßung mit Budget oben, Artikel als Vollbild mit Größenknöpfen.
- Anmeldeseite des Shops im gleichen Stil.
- Mustershop und Probeshop laufen jetzt im Rahmen der neuen Website (gleicher Kopf und Fuß) und haben das neue Shop-Design.
- Neue Texte in allen sechs Sprachen.
- Technik: neue Datei assets/shop-pro.css (liegt über dem bisherigen Shop-Stil). Ohne JavaScript bleibt die Größenauswahl als Auswahlfeld nutzbar; Bewegungen werden bei „Bewegung reduzieren“ abgeschaltet.

## 10.20.0 – Neue Website „Werkstatt-Premium“ mit Kundenleiste
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

Umsetzung des freigegebenen Konzepts, Paket 1 von 4 (Website). Firmenshops, Mustershop, Probeshop und Verwaltung sehen aus wie bisher.
- Alle Website-Seiten neu gestaltet: Startseite, Leistungen, So funktioniert’s, Über uns, Kontakt, Anfrage, Kundenlogin, Impressum, Datenschutz. Navy und Orange, die gestrichelte Naht als Erkennungszeichen, eigene Schriften (Archivo, Manrope, JetBrains Mono, liegen auf dem eigenen Server, keine Google-Verbindung).
- Startseite: großer Kopfbereich mit Werkstattfoto und schwebenden Karten (Freigabe, Veredelung, Budget, Status), Faktenband ohne Zahlen, Übersicht „Alles in einem Shop“, zwei Bestellwege, Werkstatt-Bereich mit Vorher-nachher-Regler (Shop-Vorschau gegen echten Stick), Ablauf in fünf Schritten mit Nahtlinie, die sich beim Scrollen füllt, Ansprechpartner, häufige Fragen, Abschluss mit Anfrage.
- Kundenleiste „Diese Kunden vertrauen uns“ ganz unten: Unter Verwaltung → Website & Design → Inhalte → „Startseite – Vertrauen“ gibt es jetzt pro Kundenfirma einen Haken. Angehakte, aktive Firmen mit Logo erscheinen automatisch mit ihrem Shop-Logo; zusätzlich lassen sich weiter Logos hochladen. Ab drei Logos läuft die Leiste durch (anhaltbar), grau und beim Darüberfahren farbig. Ohne Logos bleibt die Leiste weg.
- Kundenstimme erscheint nur, wenn sie in der Verwaltung gepflegt ist. Der Ansprechpartner zeigt ohne Eintrag den Inhaber aus dem Impressum.
- Texte: Produktion in Paderborn genannt (in 10.23.0 korrigiert: Salzkotten), keine Angaben zu Stichzahlen oder Motivgrößen, keine Kennzahlen.
- Technik: Die Website lädt nur noch eine Stylesheet-Datei (assets/web.css) und ein kleines Skript (assets/web.js) statt vier Stylesheets. Bewegungen werden bei „Bewegung reduzieren“ abgeschaltet; ohne JavaScript ist alles sichtbar.

## 10.19.2 – Befunde aus dem echten Shop und der Verwaltung
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

- Firmenshop: Ist die Firmenfarbe ein heller Grauton (z. B. bei WDP #9ca0a5) oder fast weiß, sahen alle Knöpfe wie gesperrt aus und der Fortschritt bei Bestellungen war kaum zu erkennen. Der Shop dunkelt solche Farben für Knöpfe und Akzente jetzt automatisch ab (WDP: #696c6f mit weißer Schrift). Kräftige Farben bleiben unverändert.
- Team-Bestellung: Der lange Regeltext stand doppelt auf der Seite (Seitenleiste und Absenden). Jetzt nur noch beim Absenden.
- Profil: „Abteilung (Verwaltung)“ hieß missverständlich nach einer Abteilung; jetzt „Abteilung (legt dein Unternehmen fest)“.
- Systemstatus: neue Prüfung „Hochgeladene Bilder vorhanden“. Sie zählt Produktbilder, Firmenlogos und Website-Bilder, deren Datei auf dem Server fehlt, nennt Beispiele und sagt, wie man sie zurückholt.
- Systemstatus „App-Schlüssel“: zeigt jetzt eine fertige Zeile zum Einfügen in config.php und erklärt, wofür der Schlüssel gut ist.

## 10.19.1 – Fehler auf der Live-Website behoben
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach Strg+F5.

Gefunden beim Durchklicken von workwearcenter.de (Stand 10.19.0, alle Dateien aktuell):
- Leistungen und So funktioniert’s: Der zweite Knopf im dunklen Seitenkopf („Mustershop ansehen“ bzw. „Mustershop testen“) war dunkel auf dunkel und damit unsichtbar. Jetzt weiß umrandet mit weißer Schrift.
- So funktioniert’s und Über uns: Zwei in der Verwaltung hinterlegte Bilder fehlen auf dem Server (uploads/branding/section2-… und section3-…). Statt eines kaputten Bildes erscheint jetzt das Standardbild. Eigene Bilder kannst du unter Verwaltung → Design neu hochladen.
- Mustershop und Probeshop: Die Fußzeile war dunkel und das Logo darauf kaum lesbar. Sie ist jetzt hell wie auf allen anderen Seiten.
- Startseite: Leistungen, So funktioniert’s und Über uns waren von der Startseite aus nicht verlinkt. Sie stehen jetzt in der Fußzeile und im Handy-Menü.

## 10.19.0 – Alltag im Firmenshop: Größen, Status, Texte
Kein Datenbank-Update nötig. Dateien hochladen (immer überschreiben), danach im Browser Strg+F5.

**Mitarbeitende**
- Größe: Steht noch keine Größe fest, zeigt das Feld „Größe wählen“. Ohne Auswahl kommt nichts in die Auswahl und die Bestellung lässt sich nicht absenden; der Artikel öffnet sich mit Hinweis am Größenfeld. Früher stand still die erste Größe (z. B. S oder XS) drin.
- Größenvorschlag: Für Artikel, die die Person noch nie bestellt hat, schlägt der Shop die Größe aus der letzten Bestellung in derselben Größenreihe vor (sonst die zuletzt bestellte Größe, wenn es sie beim Artikel gibt). Darunter steht „Vorschlag aus deiner letzten Bestellung – bitte prüfen.“
- Startseite: Die zweite Karte „Kollektion“ heißt jetzt „Neue Bestellung“, der Link über den Artikeln „Alle Artikel“. Der Eigenanteil steht nicht mehr doppelt.
- Bestellungen: Neben der Uhrzeit stand „· Neu“ (interner Fertigungsstatus), obwohl der Fortschritt darunter angezeigt wird – entfernt. Offene Bestellungen erklären, dass sie auf die Freigabe warten. Team-Bestellungen zeigen „Team-Bestellung von …“.
- Umtausch & Rückgabe: Ist ein Auftrag nachweislich in Arbeit (Status aus Produktion bzw. Auftragszentrale), beginnt die Frist erst mit dem Versand. Vorher lief sie ab Freigabe und konnte bei langer Produktion vor der Lieferung ablaufen. Zurückgeben geht erst nach Versand, Größe tauschen schon vorher (auch serverseitig geprüft).
- Warenkorb: interner Hinweis „Rechnungen … in Lexware Office“ entfernt.
- Handy: Im Bereich 1061–1100 px Breite waren die Artikel 0 px breit (aus 10.16.23) – behoben. Die untere Leiste zeigt Kurzbezeichnungen (Start, Produkte, Bestellungen, Team, Freigaben, Personen), damit nichts abgeschnitten wird.

**Verantwortliche**
- Seite „Mitarbeiter“ (hieß „Firmenkunden-Cockpit“): Personenliste mit Suche steht jetzt oben, danach Anlegen und CSV-Import, Auswertungen und Lohnabzug unten unter „Auswertungen“.
- Freigaben: Ist nichts offen, steht das oben. Team-Bestellungen zeigen, wer sie aufgegeben hat.

**Website und Verwaltung**
- Leistungen, So funktioniert’s, Über uns, Kunden-Login, Mustershop, Probeshop, Anfrage-Bestätigung per Mail und die Suchmaschinen-Beschreibungen sprechen jetzt wie die Startseite mit „du“. Impressum und Datenschutz bleiben unverändert (rechtliche Prüfung offen).
- Admin → Inhalte: Die Felder für Startseite und Unterseiten hatten seit 10.16.23 keine Wirkung mehr (Texte sind fest im Seitendesign). Sie sind entfernt, ein Hinweis erklärt das. Footer, Impressum, Datenschutz und Ansprechpartner bleiben bearbeitbar. Früher gespeicherte Texte bleiben in der Datenbank erhalten.

**Getestet** in der Testinstallation, Computer und Handy (360/390 px): Bestellung ohne Vorgeschichte (Größe wählen, Hinweis, Sperre beim Absenden), Größenvorschlag nach erster Bestellung, Bestellung und Freigabe, Team-Bestellung mit Vier-Augen-Prüfung, Umtausch während Produktion inkl. direkt gesendeter Rückgabe (abgelehnt), Mitarbeiter-Seite, Freigaben, alle Shop-Seiten in allen sechs Sprachen ohne fehlende Texte, Layoutprüfung 360–1440 px ohne seitliches Scrollen, öffentliche Website, Anfrage mit Probeshop, Verwaltungsseiten, Admin → Inhalte speichern, Sicherheits-Regressionstests grün.

## 10.18.0 – Deine Weiterentwicklung (10.16.23) und 10.17.0 zusammengeführt
**Datenbank-Update nötig:** Dateien hochladen (immer überschreiben), danach als Administrator https://workwearcenter.de/update.php aufrufen. Bis dahin zeigt der Shop „Update erforderlich“.

Enthält alles aus 10.16.1 bis 10.16.23 (neue Website, Logo und Farben, Verwaltung nach Arbeitsabläufen, Mitarbeiter-Freigaben, Team-Bestellung, Fehlerprotokoll) und alles aus 10.17.0 (Seite „Schnittstellen“, Schlüssel in der Verwaltung, Abrechnung im Tagesgeschäft).

**So wurde zusammengeführt**
- Verwaltungsmenü: die aufklappbaren Bereiche aus 10.16.23 mit „Mitarbeiter-Freigaben“. Übergabe, API und Lexware-Verbindung stehen wie in 10.17.0 gemeinsam unter System → Schnittstellen, die Rechnungen unter Aufträge & Produktion → Abrechnung. Alte Links führen automatisch dorthin.
- Systemstatus: die Prüfungen aus 10.17.0 mit Knopf zum Beheben, dazu die neuen Prüfungen aus 10.16.23 (PHP-Erweiterungen und -Werte, Upload-Ordner, privater Datenordner, App-Schlüssel, unbehandelte Fehler mit Referenzliste). Mail- und Übergabefehler zählen beim Menüpunkt Schnittstellen.

**Bei der Prüfung gefunden und behoben**
- Team-Bestellung: „Mitarbeiter hinzufügen“ ging nicht (Fehler im Skript), jetzt behoben.
- Team-Bestellung: Wer für andere bestellt, kann diese Aufträge nicht selbst freigeben, solange es eine weitere verantwortliche Person gibt (Vier-Augen-Prinzip wie bei eigenen Bestellungen). Team-Bestellungen zahlt die Firma ganz; ein Eigenanteil per Lohnabzug entsteht nur, wenn die Person selbst bestellt.
- HTTPS: Läuft die Website beim Hoster hinter einem Proxy, der nicht in `trusted_proxies` steht, entsteht keine endlose Umleitung mehr (die Seite wäre sonst nach dem Update nicht erreichbar gewesen). Der Systemstatus nennt dann die Proxy-Adresse zum Eintragen.
- Öffentliche Website: Platzhalter wie „[ANTWORTZEIT BESTÄTIGEN]“ oder „[MINDESTBESTELLMENGE EINTRAGEN]“ waren für Besucher sichtbar. Sie sind durch Texte ohne feste Zusagen ersetzt; der leere Referenzbereich wird ausgeblendet, bis Referenzen gepflegt sind. Das Werkstattbild auf „Leistungen“ wird wieder angezeigt.
- Verwaltung am Handy: Das Menü lief seitlich aus dem Bild und die Seite ließ sich seitlich verschieben, jetzt untereinander mit aufklappbaren Bereichen. Text im Suchfeld und Fußzeilen-Links waren weiß auf hellem Grund.
- Privater Datenordner gilt als geschützt, wenn er von außen gesperrt ist (Standard `app/private`), statt dauerhaft als Warnung.

**Getestet** in einer Testinstallation (PHP 8.3, MariaDB): Neuinstallation und Update-Assistent, alle Verwaltungsseiten am Computer und Handy ohne Fehler und ohne seitliches Scrollen, Daiber-Artikel anlegen mit automatischen Bildern, Firmenshop anlegen, Verantwortliche einladen, Registrierung und Freigabe über „Mitarbeiter-Freigaben“, Bestellung mit Eigenanteil, Freigabe im Shop, Team-Bestellung mit Vier-Augen-Prüfung (zweite verantwortliche Person gibt frei), alle Seiten der Website am Computer und Handy, Anfrage mit Logo und Probeshop, Mustershop, Cronjob, Sicherheits-Regressionstests grün.

## 10.16.23 – Öffentliche Website: klare B2B-Texte und neues Farbsystem
Kein Datenbank-Update nötig.

- Die Startseite erklärt in fünf konkreten Schritten den Weg von der Anfrage bis zu Produktion, Lieferung und Auftragsverwaltung.
- Die Texte richten sich direkt an Unternehmen und sprechen Kundinnen und Kunden mit „du“ an. Preis-, Mengen-, Einrichtungs-, Liefer- und Antwortzeiten bleiben sichtbar als Platzhalter, bis sie bestätigt sind.
- Der Startseiten-Hero zeigt den Ablauf als Text statt einer ungeprüften Produktbild-Collage. Navy, Orange und Weiß ersetzen die warmen Beige- und Anthrazitflächen; Navigation und Abschnittsreihenfolge bleiben bestehen.
- Anfrageformular und Impressumsanzeige enthalten keine Telefonnummer. Shop-, Login- und Bestellfunktionen bleiben unverändert.

## 10.16.22 – Anthrazitfarbene Wortmarke auf hellen Flächen
Kein Datenbank-Update nötig.

- Die helle Wortmarke des Original-Logos wird auf Website, Verwaltung und Anmeldeseiten anthrazitfarben dargestellt; das leuchtende Orange und die transparenten Bereiche bleiben erhalten.
- Helle Logo-Flächen kommen ohne dunkle Plakette aus. Auf dunklen Fußzeilen und Wartungsseiten bleibt die weiße Originalvariante im Einsatz.
- E-Mails verwenden ebenfalls die weiße Variante auf Anthrazit.

## 10.16.21 – Markenorange aus dem Original-Logo
Kein Datenbank-Update nötig.

- Die Plattform verwendet jetzt das leuchtende Orange aus dem WorkwearCenter-Logo zusammen mit Anthrazit und warmen hellen Flächen.
- Aktionsflächen zeigen dunkle, gut lesbare Beschriftungen; kleine Textlinks bleiben anthrazit und nutzen Orange als Akzent.
- Helle Bereiche wie der Footer zeigen die weiße Wortmarke auf einer passenden Anthrazitfläche.
- Gespeicherte Standardfarben der vorherigen Palette werden auf das Markenorange umgestellt; individuelle Firmen-Shop-Farben bleiben erhalten.

## 10.16.20 – Originales WorkwearCenter-Logo
Kein Datenbank-Update nötig.

- Das bereitgestellte WorkwearCenter-Logo ersetzt das generierte Standard-Wortzeichen auf Website, Verwaltung und Anmeldeseiten.
- Die weiße Wortmarke erhält auf hellen Flächen eine Anthrazitfläche; auf dunklen Fußzeilen bleibt das Logo direkt lesbar.
- Alte gespeicherte Standard-Logos werden auf das Original abgebildet. Hochgeladene Kundenlogos bleiben unverändert.

## 10.16.19 – Warmweiß, Anthrazit und Workwear-Orange
Kein Datenbank-Update nötig.

- Das Plattformdesign wechselt von kühlen Blautönen zu warmen hellen Flächen, Anthrazit und einem klaren Orange für Aktionen.
- Wortmarke und Seitensymbol passen nun zur neuen Farbwelt. Alte Standard-Farbeinstellungen werden auf die neue Plattformpalette abgebildet; hochgeladene Firmenlogos und individuelle Firmenshop-Farben bleiben erhalten.
- Anmeldungen, Verwaltung, öffentliche Website und System-E-Mails verwenden dieselbe Markenpalette.

## 10.16.18 – Firmenportal ausführlicher erklärt
Kein Datenbank-Update nötig.

- Die Startseite erklärt nun konkreter, warum das Portal für wiederkehrende Firmenkleidung gedacht ist und wie es Beschäftigte, Teamverantwortliche und Auftragsbearbeitung verbindet.
- Die Vorteile beschreiben den praktischen Nutzen einer abgestimmten Kollektion, zugeordneter Bestellangaben und persönlicher Bestellregeln.
- Das FAQ gibt ausführlichere Antworten zum Anlass des Portals, zu Einzel- und Team-Bestellungen sowie zu Regeln, Auftragsverwaltung und Einrichtung.
- Die Fußnavigation verlinkt direkt auf Vorteile und FAQ.

## 10.16.17 – Erklärbereich, Vorteile und FAQ für Firmenkunden
Kein Datenbank-Update nötig.

- Die Startseite erklärt ausführlicher, was das WorkwearCenter als geschützter Firmen-Bestellshop leistet und warum es für wiederkehrende Bestellungen eingerichtet wurde.
- Ein eigener Vorteilebereich beschreibt abgestimmte Kollektionen, nachvollziehbare Einzel- und Teamaufträge sowie Regeln je Mitarbeiterkonto.
- Eine neue FAQ beantwortet Fragen zu Mitarbeiter- und Sammelbestellungen, Budgets, Freigaben, Firmenlogo und Farben, Auftragsübergabe, Abrechnung, Lieferscheinen, Zugang und Einrichtung.
- Die Navigation führt direkt zu Vorteilen und FAQ. Das Akkordeon ist tastaturbedienbar und auf schmalen Bildschirmen einspaltig angeordnet.

## 10.16.16 – Eisblau, Anthrazit und neues WorkwearCenter-Wortzeichen
Kein Datenbank-Update nötig.

- Das Plattformdesign verwendet jetzt Eisblau für helle Flächen, Anthrazit für Text und Navigation, klares Blau für Aktionen und Rot nur als kleinen Markenakzent.
- Das WorkwearCenter-Standardlogo wurde als skalierbares Vektor-Wortzeichen neu gestaltet und auf der Website sowie in Anmeldeseiten eingebunden.
- Verwaltungsfarben, Fokuszustände, Statushinweise und globale Seiten folgen derselben Palette. Individuelle Farben und Logos der Firmen-Shops bleiben erhalten.
- Die Website verwendet das passende neue Markensymbol als SVG-Favicon.

## 10.16.15 – WorkwearCenter als eigenständiger Firmen-Bestellshop
Kein Datenbank-Update nötig.

- Die Startseite erklärt WorkwearCenter als digitalen Bestellshop für Firmenkleidung. Sichtbare Unternehmens- und Werkstattwerbung für Yanik Textildruck wurde aus der Startseite entfernt; Impressum und Datenschutz bleiben erreichbar.
- Der Einstieg zeigt eine echte Beispielkollektion und führt zu zwei Bestellwegen: Mitarbeitende bestellen selbst oder eine verantwortliche Person erfasst Aufträge für das Team.
- Der Übergang in die Auftragsverwaltung, einschließlich Abrechnung und Lieferscheinen, ist als eigener Ablauf erklärt.
- Die Seite führt ohne doppelte FAQ-Erklärungen vom Produktzweck über Bestellmodell und Auftragsweg direkt zu Kontakt und Anfrage.
- Der Aufbau nutzt eigene Produkt- und Informationsbereiche statt wiederholter Kartenabschnitte. Kundenlogos erscheinen weiterhin nur, wenn sie im Inhalt gepflegt und freigegeben sind.

## 10.16.14 – Markengerechte Blaugestaltung für Website und Verwaltung
Kein Datenbank-Update nötig.

- Das plattformweite Farbsystem greift das Dunkelblau des WorkwearCenter-Logos auf, ergänzt es mit hellblauen Flächen und setzt Orange sparsam als Logo-Akzent ein.
- Startseite, Bestellwege, Ablauf, Kontakt- und Zugangsseiten folgen jetzt derselben Typografie, Oberflächen- und Abstandslogik.
- Die Navigation der öffentlichen Seiten bleibt sichtbar und im Dokumentfluss; damit verschwindet sie nicht mehr beim Einstieg auf die Startseite.
- Verwaltungsoberfläche, Kennzahlen, Tabellen und Formulare verwenden dieselben kühlen, hellblauen Neutralflächen, feinere Rahmen und klare Statusfarben.
- Individuelle Kundenfarben, Firmenlogos sowie die bestehenden Shop- und Auftragsfunktionen bleiben erhalten.
- Die HTTPS- und Proxyprüfung liegt jetzt als eigenständige, datenbankfreie Funktion vor; der Regressionstest startet auf Windows seine Prüfprozesse ohne fehleranfällige Shell-Quotierung.

## 10.16.13 – Ruhigeres Markendesign und mobile Bestellführung
Kein Datenbank-Update nötig.

- Die WorkwearCenter-Seiten nutzen eine helle, redaktionelle Bildsprache mit klarer Typografie, warmen neutralen Flächen und gezielten Navy-/Orange-Akzenten statt großer dunkler Vollflächen.
- Der KANTENWERK-Mustershop hat ein eigenes, zurückhaltendes Waldgrün-/Messing-Farbsystem mit passendem Wortlogo und dezenter Kleidungsmarkierung.
- Produktkarten, Auswahl und Budget sind flacher und besser gegliedert. Auf schmalen Displays stehen Produktbild und Bestellfelder gut lesbar untereinander statt in gedrängten Mini-Spalten.
- Die Verwaltung verwendet eine reduzierte Flächen- und Kartenhierarchie; die Kopfleiste bleibt beim Bearbeiten langer Listen erreichbar.

## 10.16.12 – Markenbild und Arbeitsnavigation
Kein Datenbank-Update nötig.

- Die Startseite nutzt die echten Werkstattfotos in einer klaren WorkwearCenter-Farbwelt aus Navy, Orange und warmem Weiß. Kopfzeile, Bild und Handlungswege sind auf Desktop und Mobilansicht abgestimmt.
- Der öffentliche Mustershop zeigt jetzt die KANTENWERK-Beispielmarke durchgängig: Wortlogo im Shop, KANTENWERK-Markierung auf den Kleidungsstücken und dazu passende Waldgrün-/Limetten-Farben.
- Die Verwaltung gruppiert Navigation und Seitenkopf nach den täglichen Abläufen. Eine direkte Suche ist auch in der Seitenleiste erreichbar.
- Widersprüchliche Plattform-Overrides entfernt, die das Kundenfarbschema des Mustershops zuvor auf WorkwearCenter-Blau-Orange zurückgesetzt haben.

## 10.16.11 – Arbeitsübersicht und Vertrauen
Kein Datenbank-Update nötig.

- Die Verwaltungsstartseite bündelt Kennzahlen, dringende Aufgaben, Produktionsstatus und Firmenkonten in einer flachen Arbeitsoberfläche. Kennzahlen führen direkt in den zugehörigen Bereich.
- Die Hauptnavigation wechselt bereits bei mittleren Bildschirmbreiten in ein vollständiges Menü, bevor Links und Kontaktaktion umbrechen.
- Der Vertrauensbereich der Startseite bleibt sichtbar. Kundenlogos und Stimmen laufen nach redaktioneller Freigabe durch; solange noch keine Referenzen gepflegt sind, führt der Bereich transparent zur persönlichen Anfrage.
- Mustershop-Farben folgen dem WorkwearCenter-Auftritt; echte Firmenvorschauen behalten weiterhin ihre eigene Marke.

## 10.16.4 – Verwaltung nach Arbeitsabläufen
Kein Datenbank-Update nötig.

- Die Navigation ist in Kunden & Anfragen, Auftragsabwicklung, Sortiment, Marke & Website sowie System & Sicherheit gegliedert. Aktive Bereiche bleiben aufgeklappt; die zugehörigen Unterseiten stehen direkt beieinander.
- Mitarbeiterregistrierungen haben jetzt einen zentralen, durchsuchbaren Freigabeeingang mit Firmennamen, Registrierungszeitpunkt und Seitennavigation.
- Zähler und Dashboard führen direkt zum Freigabeeingang statt zur allgemeinen Kundenliste.
- Freigabe und Ablehnung prüfen den offenen Status beim Speichern erneut. Ein bereits bearbeiteter oder zwischenzeitlich veränderter Eintrag kann nicht stillschweigend ein zweites Mal verarbeitet werden.
- Freigabeentscheidungen bleiben im Aktivitätsprotokoll dokumentiert und lösen weiterhin die vorhandene Benachrichtigung aus.

## 10.16.3 – Mustershop mit eigener Firmenmarke
Kein Datenbank-Update nötig.

- Der Mustershop zeigt mit KANTENWERK eine klar als Beispiel gekennzeichnete Firmenmarke mit eigenem Logo und einer vom WorkwearCenter abweichenden Waldgrün-/Limetten-Farbwelt.
- Firmenlogo erscheint als Brustplatzierung auf den Produktfotos; Hero-Bereich, Guthabenkarte, Filter und Produktkarten folgen derselben Markenwelt.
- Größe und Menge lassen sich pro Produkt mehrfach zusammenstellen; gleiche Größen werden zusammengefasst und das Beispielbudget mitgeführt.
- Produktfilter melden ihren gewählten Zustand barrierearm; der Mustershop bleibt eine Vorschau ohne echte Bestellung.

## 10.16.2 – B2B-Website und Mustershop überarbeitet
Kein Datenbank-Update nötig.

**Öffentliche Website**
- Startseite und Unterseiten nutzen jetzt eine zusammenhängende Gestaltung: helle Seitenköpfe, lesbare Typografie, ruhigere Karten und besser erkennbare Kontaktwege.
- Die Startseite hebt Produktion, Firmenshop, Budgets und persönliche Betreuung klar hervor.
- Die enge, eingebettete Shop-Seite wurde durch eine native Produktkollektion-Vorschau mit echten Katalogbildern ersetzt. Der vollständige Mustershop bleibt über einen klaren Link erreichbar.
- Die mobile Shop-Darstellung nutzt denselben visuellen Aufbau statt einer eingebetteten Browserseite.

**Mustershop**
- Fiktive Firma, Person, Kostenstelle und nicht nutzbare Demo-Menüs entfernt. Preise, Budget und Auswahl sind ausdrücklich als Beispiele gekennzeichnet.
- Größe und Menge lassen sich wählen; die Vorschau berechnet Auswahl, Restbudget und Entfernen/Leeren ohne echte Bestellung.
- Auf Produktkarten erscheinen Katalogbild, Kategorie, Farbe, Größenbereich und Beispielpreis; das Layout passt sich an Smartphone und Desktop an.

**Betrieb**
- Keine Schemaänderung. Release-Prüfsummen und Abnahme-Checkliste aktualisieren.

## 10.16.1 – Vertrauenswürdige Vorschau und sichererer Betrieb
Kein Datenbank-Update nötig: Programmdateien vollständig hochladen und vorhandene Dateien überschreiben.

- Startseite und Leistungsseite zeigen jetzt eine aktuelle Kollektion-Vorschau; Beispielinhalte sind klar gekennzeichnet.
- Social-Media-Vorschau und LocalBusiness-Daten verwenden das eingestellte Firmenlogo statt eines alten Firmenshop-Bildes.
- Die Betriebsprüfung erkennt jetzt auch fehlende `fileinfo`, `dom` und `xmlreader`, die sichere Datei-Uploads, SVG-Prüfung und den Excel-Katalogimport benötigen.
- Fehlerdetails gelangen nicht mehr in eine öffentlich erreichbare Datei. Unerwartete Fehler erhalten eine Support-Referenz und werden gekürzt und bereinigt im privaten Datenordner protokolliert; Systemstatus und Update-Assistent zeigen sichere Fehlerhinweise. API-Aufrufer erhalten bei einem internen Fehler strukturiertes JSON mit Referenz statt einer leeren HTML-Fehlerseite.
- `X-Forwarded-Proto` gilt nur noch von ausdrücklich eingetragenen Reverse-Proxys als vertrauenswürdig. Direkte HTTPS-Werte werden eng geprüft.
- Zwei ungenutzte Hilfsfunktionen ohne Wirkung entfernt.

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
- Schwere PHP-Fehler der Website wurden in `app/letzter-fehler.txt` festgehalten. Ab Version 10.16.1 wird dieses veraltete Protokoll durch das private, rotierte Laufzeitprotokoll ersetzt.
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

## 10.14.0 – Sicherheit und Belastbarkeit für Großkunden
Vor dem ersten Großkunden wurde das System in drei Richtungen geprüft: Zugriffsschutz, Angriffe über Eingaben und Dateien, und ein Großkunde mit 1.500 Mitarbeitern und 20.000 Bestellungen. **Nach dem Hochladen unbedingt `/update.php` ausführen** (neue Datenbank-Indizes). Neu im Paket: `GO-LIVE-CHECKLISTE.md`.

**Sicherheit**
- Anmeldung: Passwort-Raten über viele IP-Adressen wird je Konto gestoppt; die Antwortzeit verrät nicht mehr, ob ein Konto existiert.
- Firmen-PIN der Registrierung und „Passwort vergessen“ sind unabhängig von der IP gegen Erraten bzw. Mail-Fluten gedrosselt.
- Zwei-Faktor- und Wiederherstellungscodes sind auch bei gleichzeitigen Anfragen nur einmal nutzbar.
- Anonymisierte Konten bleiben endgültig gesperrt.
- API-Schlüssel lassen sich auf eine Firma beschränken (Partner, Fan-Bande).
- SVG-Logos: Skripte über XSLT und externe Verweise werden entfernt.
- Interne Ordner (`app/`, `tests/`, `tools/`) und versteckte Dateien sind auch ohne mod_rewrite gesperrt; der Systemstatus prüft das mit.
- Vorschau, Vorschau beenden und Datensicherung lassen sich nicht von fremden Seiten auslösen.
- Sitzungen enden spätestens 12 Stunden nach der Anmeldung; `/api/v1/ping` nennt die Version nur mit Schlüssel.
- Katalog-ZIPs werden höchstens bis 40 MB je Datei und 1 GB gesamt entpackt.
- Die Verwaltung zeigt einen roten Hinweis, solange nach einem Upload `update.php` nicht gelaufen ist.

**Großkunden-Tauglichkeit**
- Datensicherung schreibt zeilenweise statt alles im Speicher (bisher 240 MB, jetzt 2 MB); Zeitzonen-Fehler bei der Wiederherstellung behoben.
- Neue Indizes: Budget- und Mengenprüfung im Shop von 1,1 s auf 0,05 s.
- Mitarbeiterlisten (Kundenakte und Firmenshop) seitenweise mit Suche.
- CSV-Exporte werden gestreamt.
- Hängt der Mailserver oder die Auftragszentrale, pausiert die Warteschlange 5 Minuten, statt jede Seite zu blockieren.
- Umtausch mit Mehrkosten kann das Budget auch bei gleichzeitigen Anfragen nicht überschreiten.
- Datenbankausfall zeigt einen verständlichen Hinweis statt einer leeren Seite.
- Volle Festplatte hinterlässt keine abgeschnittenen Uploads.
- Einrichtungslinks neuer Konten gelten 7 Tage (große Importe).

## 10.15.0 – Produktbilder automatisch, Vier-Augen-Schutz, Großkunden-Tauglichkeit
**Nach dem Hochladen unbedingt `/update.php` ausführen** (neue Spalten und Indizes). Bis dahin zeigt die Verwaltung den roten Hinweis „Datenbank-Update fehlt“ und der Firmenshop „Update erforderlich“.

**Produktbilder kommen automatisch aus dem Daiber-Shop**
- Nach dem Anlegen von Katalogprodukten lädt das System die Bilder jeder Farbe selbst von daiber.shop: vorne, hinten und Ärmel (Ärmel und Rücken nur bei Oberteilen bzw. Hosen). Kein Login, keine Dateinamen, kein Hochladen nötig.
- Der Cronjob ergänzt alle 5 Minuten, was noch fehlt – auch für alle schon vorhandenen Produkte (Rückseiten- und Ärmelfotos inklusive). Druckzonen werden danach automatisch erkannt.
- Katalog → Produktbilder: Knopf „Fehlende Bilder jetzt laden“, Schalter „Automatisch laden“ und Stand des letzten Abgleichs; beim Artikel „Bilder von Daiber laden“ für einzelne Artikel.
- Eigene Fotos haben immer Vorrang. Artikel, die der Daiber-Shop nicht führt, werden genannt (dann wie bisher hochladen).
- Das Logo am linken Ärmel erscheint auf dem Ärmelfoto, das rechte bleibt vorne – zwei Ärmel-Logos liegen nie übereinander.
- Sicher: Verbindungen nur per HTTPS zu den drei festen Daiber-Adressen, nur echte Bilder werden gespeichert (verkleinert auf 1.400 px), bei Störungen pausiert der Abgleich 15 Minuten.

**Vier-Augen-Schutz für Verantwortliche**
- Verantwortliche können andere Verantwortliche nicht herabstufen, sperren, löschen oder deren Ein-/Austritt ändern – auch nicht per CSV oder manipuliertem Formular. Das macht nur die Verwaltung.
- Neue Einstellung je Firma (Kundenakte → Regeln): „Verantwortliche dürfen weitere Verantwortliche ernennen“. Standard: aus.
- Eigene Bestellungen und Umtausch-Anträge gibt immer eine andere verantwortliche Person frei (im Shop und per Mail-Link). Gibt es nur eine, darf sie selbst freigeben; das wird protokolliert.
- Rollenänderungen und abgewiesene Versuche stehen 14 Tage im Dashboard unter „Heute zu erledigen“ und in der Kundenakte.
- Menü-Zähler, Startseite und Freigabeseite zählen nur, was wirklich auf die eigene Entscheidung wartet.

**Erinnerungen und Aufbewahrung**
- Statt einer Mail je offener Bestellung bekommen Verantwortliche höchstens einmal am Tag eine Sammel-Erinnerung (offene Freigaben und Registrierungen, neue markiert, in ihrer Sprache) – und nur, wenn seit der letzten etwas Neues dazugekommen ist.
- Das Aktivitätsprotokoll wird nach 24 Monaten automatisch gelöscht (einstellbar 6–120 Monate unter System → Systemstatus), versendete Mails nach 90, fehlgeschlagene nach 180 Tagen. Aufträge, Lexware- und Lohnabzug-Einträge bleiben immer.
- Anfrageformular: Die Eingangsbestätigung geht höchstens einmal in 24 Stunden an dieselbe Adresse (auch mit +Zusatz oder Gmail-Punkten) und höchstens 30-mal je Stunde. Die Anfrage selbst kommt immer bei Yanik an.

**Lexware**
- Monats-Sammelrechnungen mit mehr als 300 Positionen werden automatisch in mehrere Entwürfe aufgeteilt (nach ganzen Kostenstellen, eine allein zu große Kostenstelle in „Teil 1 von 3“). Die Vorschau zeigt vorher genau, welche Entwürfe entstehen.
- Ein Auftrag steht immer komplett in einem Entwurf und wird genau einmal berechnet. Scheitert ein Teil, wird nur dieser mit „Erneut senden“ nachgeholt.
- Viele Entwürfe (z. B. 31 bei „je Kostenstelle“) gehen mit einem Klick raus; die Seite sendet den Rest selbst weiter.

**Großkunden (1.500 Mitarbeitende, 20.000 Aufträge)**
- Kundenakte „Kollektion & Design“: kompakte Artikelliste mit Suche, Kategorie und Seiten; das Bearbeitungsformular öffnet sich nur für den gewählten Artikel (Grossbau: 3,1 MB → 113 KB).
- „Regeln & Budget“: Produktfreigaben je Profil öffnen sich auf Klick, mit Seiten und Sammelaktionen (926 KB → 131 KB).
- Nach dem Speichern bleibt man in der Kundenakte und im Firmenshop unter „Mitarbeiter“ auf derselben Seite mit derselben Suche; die Rückmeldung steht direkt am bearbeiteten Eintrag.
- Schneller: Verwaltungs-Startseite etwa 3×, Übergabe etwa 4×, Kundenakte etwa 2× so schnell; die Datenbankprüfung läuft nicht mehr bei jedem Seitenaufruf vollständig. Neue Indizes für Mail- und Übergabewarteschlange und Lohnabzug.

**Übersetzungen**
- Englisch, Polnisch, Türkisch, Russisch und Rumänisch gegengelesen und vereinheitlicht (z. B. Anrede in Mails, Mengen- und Budgettexte, Passwort-Seiten).

## 10.16.0 – Neues Design der Firmenshops
Kein Datenbank-Update nötig: Dateien hochladen (immer überschreiben), fertig.

**Kopfzeile statt Seitenleiste**
- Die farbige Seitenleiste ist weg. Oben steht eine helle Kopfzeile mit Firmenlogo, Menü, verfügbarem Budget, Sprache und Konto-Menü (Profil, Abmelden) – wie in einem Onlineshop.
- Die Firmenfarbe erscheint nur noch als schmale Linie oben, bei Knöpfen und beim aktiven Menüpunkt. So wirkt jede Firmenfarbe ruhig, auch sehr kräftige.
- Am Handy liegt das Menü als Leiste unten (Übersicht, Produkte, Bestellungen, bei Verantwortlichen auch Freigaben und Mitarbeiter, mit Zähler).

**Kollektion**
- Große Produktbilder auf ruhigem Grau, ohne Rahmen und ohne weiße Bildkästen (weiße Fotohintergründe verschmelzen mit der Fläche).
- Kategorien bleiben beim Scrollen oben stehen.
- „Deine Auswahl“ mit Budget, Positionen und Summen läuft am Computer rechts immer mit; auf kleineren Bildschirmen steht sie wie bisher unter der Kollektion.
- Am Handy zwei Artikel nebeneinander; der Artikel öffnet als Vollbild mit festem Knopf „In die Auswahl“ unten.

**Startseite, Bestellungen, Anmeldung**
- Startseite mit Begrüßung, Budget und den ersten Artikeln der Kollektion (Klick öffnet den Artikel).
- Bestellungen und Freigaben als aufgeräumte Karten mit Status-Punkt, Fortschritt und Summen.
- Anmeldung, Registrierung und Passwort-Seiten: links Logo und Formular, rechts Fläche in Firmenfarbe mit Bildern aus der eigenen Kollektion (am Handy nur das Formular).
- Mustershop und Probeshop auf der Website zeigen dasselbe Erscheinungsbild.

Alle Funktionen, Preise, Budgets, Regeln und Freigaben sind unverändert.

## 10.17.0 – Reiter „System“ aufgeräumt: alle Schnittstellen an einem Ort
Kein Datenbank-Update nötig (wenn 10.15 schon eingespielt ist): Dateien hochladen (immer überschreiben), fertig. Enthält das neue Shop-Design aus 10.16.0.

**Vorher:** Unter System gab es sieben Unterpunkte. Die Auftragszentrale war auf „Übergabe“ und „API-Zugänge“ verteilt, die API-Seite verwies wieder zurück auf „Übergabe“, der Systemstatus zeigte Mailversand und einen „Schnittstellenstatus“ mit Dauerwarnungen zu L-Shop (gibt es nicht) und einem Hinweis „Keine Textillagerpflicht“ (kein Status), Lexware stand doppelt (Systemstatus und eigene Seite), und eingeschaltet wurde alles nur über die config.php per FTP.

**Jetzt: System hat fünf Unterpunkte**
- **Systemstatus** – nur noch Betriebsprüfung, Datensicherung und Aufbewahrung. Offene Punkte stehen oben, jeder mit Knopf dorthin, wo er sich beheben lässt („Jetzt sichern“, „Einrichten“, „Ergänzen“, „Jetzt prüfen“ …).
- **Schnittstellen** (neu) – jede Verbindung zu einem anderen System genau einmal, oben als Ampel-Übersicht:
  - *Auftragszentrale*: Übergabe und Rückmeldung (Produktionsstand, Sendungsnummer) in einer Karte, mit Zählern, „Verbindung testen“, „Fehlgeschlagene erneut senden“ und dem Übergabeprotokoll darunter.
  - *E-Mail-Versand*: Versandweg, Warteschlange, Testmail, fehlgeschlagene Mails.
  - *Lexware Office*: nur die Verbindung.
  - *Zugänge für andere Systeme (API)*: Schlüssel für Fan-Bande und Partner.
  - *Daiber-Produktbilder*: Zustand, Verweis auf den Lieferantenkatalog.
- **Administratoren**, **Aktivitäten**, **Anleitung** – unverändert.
- **Abrechnung** (Lexware-Rechnungsentwürfe, Firmen verknüpfen, Monats-Sammelrechnung) ist ins Tagesgeschäft gewandert – das ist Arbeit, kein Systemthema.

**Einrichten ohne FTP**
- Auftragszentrale und Lexware Office lassen sich direkt in der Verwaltung verbinden: Schlüssel einfügen, eigenes Admin-Passwort bestätigen, speichern. Die Verbindung wird sofort geprüft; bei der Auftragszentrale gehen gesammelte Aufträge danach gleich raus (auch Aufträge, die vorher mit einem falschen Schlüssel gescheitert waren).
- Zugangsdaten liegen verschlüsselt (AES-256-GCM) in der Datenbank, der Schlüssel dazu im privaten Datenordner (`schnittstellen.key`, nicht in der Datensicherung). Eine gestohlene Datenbanksicherung allein verrät sie nicht. Angezeigt werden sie nie wieder.
- Jede Änderung braucht das eigene Admin-Passwort, steht im Aktivitätsprotokoll und wird allen Administratoren per E-Mail gemeldet.
- Was in der config.php steht, gilt weiter und hat Vorrang (die Seite zeigt es dann nur an). Bestehende Installationen laufen also unverändert weiter.

**Doppeltes entfernt**
- Der alte Abholweg `/api/v1/auftraege` mit eigenem `handover_api_token` wird nicht mehr angeboten (läuft aus der config.php weiter, falls eingetragen); abholen geht mit einem normalen API-Zugang.
- Alte Lesezeichen (`?tab=handover`, `?tab=api`) führen automatisch zur neuen Seite.

**Getestet** gegen nachgebaute Auftragszentrale (Signaturprüfung wie channel.php) und Lexware-Testserver: falsches Passwort, http-Adresse, zu kurzer und falscher Schlüssel werden abgelehnt; richtiger Schlüssel verbindet, Aufträge kommen mit YT-Nummer zurück; signierte Statusrückmeldung mit dem in der Verwaltung hinterlegten Schlüssel setzt Produktionsstand und Sendungsnummer (Wiederholung 409, falsche Signatur 401); Lexware verbinden und trennen; API-Zugang anlegen und abfragen; config.php-Vorrang; fehlende Schlüsseldatei wird klar gemeldet; alle Verwaltungsseiten laden; Handy ohne seitliches Scrollen; Sicherheits-Regressionstests grün.
