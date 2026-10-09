# WorkwearCenter 9.0.1

## Korrekturstand 9.0.1

- Das Startseitenfoto wurde professionell bereinigt; Schneidematte und unruhiger Arbeitsbereich sind nicht mehr sichtbar.
- Die Bildbeschriftung liegt innerhalb der Bildfläche und erzeugt auch mobil keinen horizontalen Überlauf.
- Lange Rollen- und Statustexte dürfen im Firmenportal umbrechen, statt abgeschnitten zu werden.
- Der Auftragsfluss im Admin reserviert ausreichend Platz für „Qualitätskontrolle“.
- Die PNG-Dateien sind ausdrücklich als Layoutansichten gekennzeichnet. Die mitgelieferte HTML-Vorschau bleibt für Verhalten und responsive Darstellung maßgeblich.

Version 9 macht aus dem gewachsenen Projekt einen belastbaren Betriebsstand für Yanik Workwear by Yanik Textildruck. Bestehende Daten und die produktive `config.php` werden nicht ersetzt.

## Sichtbares Design

- Neue redaktionelle Startseite mit kompaktem Einstieg statt übergroßer Bildbühne.
- Originales Yanik-Workwear-Logo, Navy/Orange und reale Produktions- bzw. Produktbilder.
- Echte T-Shirt-, Hoodie- und Softshell-Ansichten im öffentlichen Mustershop.
- Einheitliches, reduziertes Erscheinungsbild für öffentliche Seiten, Firmenshops, Admin und E-Mails.
- Responsives Layout für Desktop, Tablet und Mobilansicht.
- Überarbeitete Adminnavigation mit verständlichen Arbeitsbereichen und SVG-Icons.

## Firmenkunden und Mitarbeiter

- Persönliche Konten statt gemeinsamer Passwörter.
- Rollen für Mitarbeiter und Verantwortliche im selben Login.
- Sichere Registrierung: offen, mit Freigabe, mit Firmen-PIN oder PIN plus Freigabe.
- Mitarbeiterpflege einzeln oder per CSV, inklusive Abteilung, Kostenstelle, Standort, Personalnummer und Beschäftigungszeitraum.
- Passwort-Einrichtung und -Rücksetzung per zeitlich begrenztem Link.
- Schutz des letzten zugangsberechtigten Verantwortlichen.
- Jahresbudget pro Mitarbeiter, individuelle Abweichungen und frei wählbarer Neustartmonat.
- Mengenlimits je Firmenprodukt und Ausstattungsprofile je Tätigkeit.

## Produkte und Corporate Identity

- Produktstamm mit Kategorie, Lieferanten-SKU, Marke, Lieferant, EK, VK, MwSt., Farbe, Größen und Bild.
- Transaktionaler CSV-Import/-Export: vollständig erfolgreich oder ohne Teiländerung.
- Firmenkollektion mit abweichendem Namen, Beschreibung, Preis sowie Vorder-/Rückansicht.
- Strukturierte Veredelungspositionen und unveränderlicher Auftragssnapshot.
- Veredelungsmotive je Firma mit Verfahren, manueller Bestandsmenge und Mindestbestand.
- Prüfung offener Druck-/Transferbedarfe gegen den Motivbestand – ohne Textillagerverwaltung.

## Auftrag und Produktion

- Serverseitige Prüfung von Budget, Stücklimit, Sortiment, Größe und Zugangsstatus.
- Doppelte Bestellungen werden über gebundene Einmal-Tokens verhindert.
- Freigabe oder Ablehnung mit Hinweis an den Mitarbeiter.
- Produktionsstatus von „Neu“ über Material, Produktion und Qualitätskontrolle bis Versand/Abholung/Abschluss.
- Interne Produktionsnotizen, Packlisten, Beschaffungsbedarf und Veredelungsbedarf.
- Beschaffungs-CSV nach Lieferant, SKU, Farbe und Größe.
- Arbeits-CSV mit Netto, Brutto, Firmenanteil, Mitarbeiteranteil und Kostenstellen.
- Bestehende Altaufträge über die frühere Einladungszuordnung bleiben sichtbar.

## Betrieb und Sicherheit

- Kumulativer, wiederholbar ausführbarer Update-Assistent mit Schema-Prüfung.
- CSRF-Schutz, sichere Sessions, HTTPS-Weiterleitung, Sicherheitsheader und geschützte Uploadordner.
- Anmelde-, Registrierungs- und Passwort-Reset-Drosselung ohne pauschale Firmensperre.
- Bildprüfung nach echtem Inhalt und Abmessungen; ausführbare Uploads werden blockiert.
- Auditprotokoll für relevante Änderungen.
- Professionelles Multipart-Maildesign, Warteschlange, Wiederholungslogik und CLI-Cronjob.
- Systemstatus für Schema, PHP, HTTPS, Mail, Datenschutz, Wartungsmodus, Lieferantendaten und SKU-Dubletten.
- Automatische Bereinigung abgelaufener Sicherheitsdaten über den Cronjob.

## Bewusst noch nicht automatisiert

- L-Shop: Beschaffungsdaten sind vorbereitet, automatische Bestellung bleibt bis zu geprüfter API-Dokumentation und Zugangsdaten deaktiviert.
- Lexware Office L: Rechnungen und Lieferscheine werden weiterhin in Lexware Office erstellt; es existiert keine vorgetäuschte Importschnittstelle.
- Fan-Bande: gemeinsame technische Basis ist vorbereitet, öffentliche Fanshops und Zahlungen sind nicht Bestandteil dieses Releases.
- Motivmengen werden geprüft, aber nicht automatisch verbraucht. Das vermeidet falsche Abbuchungen bei Ausschuss, Nachdruck oder Statuskorrekturen.
