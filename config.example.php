<?php
declare(strict_types=1);

/*
 * Vorlage für eine neue Installation.
 * Die produktive Datei heißt config.php und darf niemals öffentlich geteilt
 * oder bei einem Update durch diese Vorlage ersetzt werden.
 * Zufällige Schlüssel erzeugen z. B. mit:  php -r "echo bin2hex(random_bytes(32));"
 */
return [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'DATENBANKNAME',
    'db_user' => 'DATENBANKBENUTZER',
    'db_pass' => 'EIN_LANGES_EIGENES_DATENBANKPASSWORT',

    // "mail" nutzt den Maildienst des Hosters, "smtp" die Angaben darunter.
    'mail_transport' => 'smtp',
    'mail_from' => 'noreply@workwearcenter.de',
    'mail_reply_to' => 'info@yanik-textildruck.de',
    'smtp_host' => 'SMTP-HOST-DES-ANBIETERS',
    'smtp_port' => 587,
    'smtp_encryption' => 'tls',
    'smtp_user' => 'SMTP-BENUTZER',
    'smtp_pass' => 'SMTP-PASSWORT',

    // Privater Datenordner (Lieferantenlisten mit Einkaufspreisen u. Ä.).
    // Am sichersten AUSSERHALB des Webroots, z. B. '/var/www/workwearcenter-privat'.
    // Leer = app/private (durch .htaccess gesperrt; nur unter Apache wirksam).
    'private_dir' => '',

    // Nur IPs/CIDRs eines Reverse-Proxys eintragen, der X-Forwarded-Proto selbst setzt.
    // Nie den Proxy-Header ohne diese Vertrauensliste akzeptieren.
    'trusted_proxies' => [],

    // Zusätzlicher Geheimschlüssel für die Freigabe-Links in E-Mails (mind. 32 Zeichen, zufällig).
    // Damit reicht eine gestohlene Datenbanksicherung allein nicht, um Freigabe-Links zu fälschen.
    // Nach dem Setzen werden bereits verschickte Freigabe-Links ungültig.
    'app_secret' => '',

    // Nur für die Neuinstallation über /install.php (mind. 16 Zeichen).
    'install_key' => '',

    // Übergabe freigegebener Aufträge an die Auftragsverwaltung.
    // 'off'  = aus (Aufträge werden trotzdem gesammelt und später übergeben)
    // 'push' = WorkwearCenter sendet an handover_url
    // 'pull' = Auftragsverwaltung holt über /api/v1/auftraege ab
    // 'both' = beides
    // Anleitung: SCHNITTSTELLE-AUFTRAGSVERWALTUNG.md
    'handover_mode' => 'off',
    // 'auftragszentrale' = Yanik Auftragszentrale (empfohlen), 'workwearcenter' = eigenes Schema v1
    'handover_format' => 'auftragszentrale',
    'handover_url' => '',          // Auftragszentrale: https://auftraege.yanik-textildruck.de/channel.php
    'handover_secret' => '',       // Auftragszentrale: Schlüssel aus „Verkaufskanäle → WorkwearCenter“
    'handover_api_token' => '',    // mind. 32 Zeichen, nur für 'pull' / 'both'

    // Lexware Office (Public API): Rechnungsentwürfe und Kundenkontakte. Leer = aus.
    // Schlüssel erzeugen: Lexware Office → Erweiterungen → Public API (https://app.lexware.de/addons/public-api)
    'lexware_api_key' => '',
    // Nur für Tests ändern (z. B. lokaler Testserver); Standard ist die offizielle Adresse.
    'lexware_api_base' => 'https://api.lexware.io',
];
