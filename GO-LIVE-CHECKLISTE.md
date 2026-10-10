# Go-Live-Checkliste WorkwearCenter (Großkunde)

Stand: Version 10.17.0 · Reihenfolge von oben nach unten abarbeiten. Alles, was hier steht, kann das Programm nicht selbst einstellen – es hängt am Hoster, an der config.php oder an deiner Einrichtung.

## 1. Hoster / Server (einmalig, ggf. Hoster-Support fragen)

- [ ] **HTTPS** mit gültigem Zertifikat für workwearcenter.de (und www). Die Seite leitet selbst auf https um.
- [ ] **PHP 8.1 oder neuer** (empfohlen 8.3), mit den Erweiterungen pdo_mysql, gd, zip, mbstring, curl, openssl.
- [ ] **PHP-Einstellungen** (php.ini oder Hoster-Oberfläche):
  - `memory_limit = 256M`
  - `max_execution_time = 300` (Datensicherung und große Exporte)
  - `upload_max_filesize = 20M`, `post_max_size = 25M`
  - `display_errors = Off`, `expose_php = Off`
  - `session.gc_maxlifetime = 14400` (sonst werden Mitarbeitende schon nach 24 Minuten abgemeldet)
- [ ] **Apache:** `AllowOverride All`, `mod_rewrite` und `mod_headers` aktiv. Ohne diese drei sind die Schutzregeln in den `.htaccess`-Dateien wirkungslos. Bei nginx die Regeln aus `START-HIER-V10.txt` übernehmen.
- [ ] **Hinter einem Proxy/CDN** (z. B. Cloudflare): Der Server muss die echte Besucher-IP weitergeben (mod_remoteip). Sonst gelten Login-Sperren für alle Besucher gemeinsam.
- [ ] **MySQL/MariaDB:** InnoDB, `max_allowed_packet ≥ 16M`, wenn möglich `innodb_buffer_pool_size ≥ 256M`.
- [ ] **Cronjob alle 5 Minuten:** `php /pfad/zu/workwearcenter/cron.php` (Mails, Übergabe, Sammel-Erinnerungen, Produktbilder, Druckzonen, Aufräumen). Ohne Cronjob gehen bei 1.500 Mitarbeitern Einrichtungs-Mails nur schleppend raus.
- [ ] **Ausgehende HTTPS-Verbindungen** zu `www.daiber.shop`, `daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net` und `daiber-images.synaigy.com` erlaubt (bei fast allen Hostern Standard). Darüber kommen die Produktbilder vorne, hinten und Ärmel automatisch.
- [ ] Ordner **`uploads/katalog/bilder`** ist für PHP beschreibbar (dort landen die Daiber-Bilder im Unterordner `daiber`).
- [ ] **Tägliche Sicherung beim Hoster** (Datenbank und Ordner `uploads/`) und mindestens 14 Tage Aufbewahrung.

## 2. Programm hochladen

- [ ] Im FileZilla **versteckte Dateien anzeigen** (Server → „Anzeige versteckter Dateien erzwingen“), damit alle `.htaccess`-Dateien mit hochgeladen werden.
- [ ] Beim Hochladen **„Überschreiben“** und „Immer diese Aktion verwenden“ wählen – nicht „nur wenn neuer“.
- [ ] Danach **`/update.php`** aufrufen und ausführen. Solange das fehlt, zeigt die Verwaltung oben einen roten Hinweis „Datenbank-Update fehlt“.
- [ ] **Verwaltung → System → Systemstatus:** alle Punkte grün, besonders „Programmdateien vollständig hochgeladen“ und „Interne Dateien gesperrt“.

## 3. config.php

- [ ] `mail_transport` auf **`smtp`** mit echtem Postfach (nicht `mail` des Hosters – landet sonst oft im Spam). SPF/DKIM für die Absenderdomain beim Mailanbieter einrichten.
- [ ] **`app_secret`** setzen: zufällige 64 Zeichen, z. B. `php -r "echo bin2hex(random_bytes(32));"`.
- [ ] `private_dir` möglichst **außerhalb** des Webroots (Lieferantenlisten mit Einkaufspreisen).
- [ ] `install_key` leeren; `install.php` kann nach der Einrichtung gelöscht werden.
- [ ] Testmail aus Verwaltung → System → Schnittstellen → E-Mail-Versand senden und ankommen lassen.
- [ ] Auftragszentrale und (optional) Lexware Office unter System → Schnittstellen verbinden; beide Karten zeigen „Aktiv“ bzw. „Verbunden“.

## 4. Zugänge

- [ ] **Zwei-Faktor-Anmeldung für jeden Admin** einrichten (Mein Konto). Wiederherstellungscodes ausdrucken und sicher ablegen.
- [ ] Jede Person bekommt ein eigenes Admin-Konto – keine geteilten Zugänge.
- [ ] **API-Schlüssel:** Für Partner und Fan-Bande immer „nur Firma …“ wählen. „Alle Firmen“ nur für die Auftragszentrale.
- [ ] **Datensicherungen** aus der Verwaltung enthalten alle Daten inkl. Zwei-Faktor-Geheimnissen: nur verschlüsselt ablegen, nicht per Mail verschicken.

## 5. Firmenshop des Großkunden einrichten

- [ ] Firma anlegen (Lieferadresse, Shopname, Farben, Logo). Danach öffnet sich direkt „Mitarbeiter“.
- [ ] **Mindestens zwei Verantwortliche** anlegen (Vertretung bei Urlaub). Das Vier-Augen-Prinzip greift dann automatisch: Eigene Bestellungen und Anträge gibt immer die andere Person frei, und Verantwortliche können sich nicht gegenseitig herabstufen oder sperren.
- [ ] Wer Verantwortliche ernennen darf: Standard ist **nur die Verwaltung**. Nur wenn der Kunde das selbst machen soll, in der Kundenakte unter „Regeln“ „Verantwortliche dürfen weitere Verantwortliche ernennen“ einschalten.
- [ ] Registrierungsweg wählen: **Freigabe durch Verantwortliche** (empfohlen) oder Firmen-PIN. Eine PIN nur lang (mind. 10 Zeichen) und nicht öffentlich aushängen – wer sie hat, bestellt auf Firmenbudget.
- [ ] Kollektion, Ausstattungsprofile, Budgets und Mengenlimits festlegen. Produktbilder (vorne, hinten, Ärmel) und Druckzonen kommen automatisch; einmal in der Vorschau („Shop ansehen“) prüfen.
- [ ] Mitarbeitende per **CSV-Import** anlegen. Einrichtungslinks gelten 7 Tage; der Cronjob verschickt sie nach und nach.
- [ ] **Probelauf:** eine Testperson bestellt, Verantwortliche/r gibt frei (auch per Mail-Link), Auftrag kommt in der Auftragszentrale an, Status kommt zurück, Umtausch einmal durchspielen. Danach Testbestellung stornieren.
- [ ] **Lexware:** API-Schlüssel eintragen und Verbindung testen. Große Monats-Sammelrechnungen (mehr als 300 Positionen) teilt das System selbst in mehrere Entwürfe auf; die Vorschau zeigt vorher, welche entstehen.

## 6. Rechtliches (vor dem ersten echten Auftrag)

- [ ] Rahmenvertrag / AGB und **Auftragsverarbeitungsvertrag (AV-Vertrag)** mit dem Großkunden unterschrieben (Entwürfe liegen vor, Anwalt prüfen lassen).
- [ ] Datenschutzerklärung und Impressum aktuell (Verwaltung → Inhalte).
- [ ] Übersetzungen (PL, TR, RU, RO) sind überarbeitet; falls der Kunde sie nutzt, trotzdem einmal von einer muttersprachlichen Person im Shop durchklicken lassen.

## 7. Im Betrieb

- [ ] Dashboard „Heute zu erledigen“ täglich ansehen (Übergabefehler, offene Freigaben, fehlgeschlagene Mails, geänderte Verantwortlichen-Rollen).
- [ ] Systemstatus nach jedem Update prüfen.
- [ ] Ausgeschiedene Mitarbeitende mit Austrittsdatum versehen oder anonymisieren (DSGVO).
- [ ] Aufbewahrung: Das Aktivitätsprotokoll wird nach 24 Monaten automatisch gelöscht (Lexware- und Lohnabzug-Einträge bleiben). Die Frist lässt sich unter System → Systemstatus ändern (6 bis 120 Monate).
