# Schnittstelle WorkwearCenter → Auftragsverwaltung

Version 10.0.0 · Datenschema `workwearcenter.order.v1`

Sobald eine Bestellung **freigegeben** ist (durch den Firmenverantwortlichen oder automatisch bei Shops ohne Freigabepflicht), erzeugt das WorkwearCenter genau **einen** vollständigen Auftragsdatensatz. Er enthält alles, was die Auftragsverwaltung braucht: Firma, Mitarbeiter, Kostenstelle, Lieferadresse, Artikel, Größen, Veredelung und Preise.

Der Datensatz wird auch dann erzeugt, wenn die Schnittstelle noch ausgeschaltet ist. Nach dem Einschalten geht also nichts verloren.

## Schnellstart: Yanik Auftragszentrale

Die Auftragszentrale hat ein eigenes Empfangsprotokoll (`channel.php`), das das WorkwearCenter direkt spricht:

1. In der Auftragszentrale unter **Verkaufskanäle → WorkwearCenter** auf **„Schlüssel erzeugen“** klicken und den Schlüssel kopieren. Er wird nur einmal angezeigt.
2. In der `config.php` des WorkwearCenters eintragen:
   ```php
   'handover_mode'   => 'push',
   'handover_format' => 'auftragszentrale',
   'handover_url'    => 'https://auftraege.yanik-textildruck.de/channel.php',
   'handover_secret' => 'DER-KOPIERTE-SCHLÜSSEL',
   ```
3. Im WorkwearCenter-Admin unter **„Übergabe“** auf **„Verbindung testen“** klicken.

Danach erscheint jede freigegebene Bestellung automatisch als Auftrag „YT-JJJJ-NNNNN“ mit der Quelle „WorkwearCenter“ in der Auftragszentrale. Die YT-Nummer wird im WorkwearCenter beim Auftrag angezeigt. Artikel, Größen, Farben und Veredelung landen in den Auftragsfeldern. Mitarbeiter, Kostenstelle, Freigabe, Lieferadresse und Beträge stehen in den Notizen. Der vollständige Originaldatensatz liegt zusätzlich im Auftrag (`order.workwearcenter`, Schema v1 unten).

Signatur dieses Formats: `X-Yanik-Signature = hex(HMAC-SHA256(Schlüssel, Zeit + "\n" + Nonce + "\n" + "POST" + "\n" + Pfad + "\n" + Body))`, mit den Headern `X-Yanik-Channel: workwearcenter`, `X-Yanik-Time` (Millisekunden) und einer einmaligen `X-Yanik-Nonce`. Erneutes Senden desselben Auftrags erzeugt keine Dublette.

Alles Weitere in diesem Dokument beschreibt das allgemeine Format `workwearcenter` für andere Zielsysteme.

## Wege der Übergabe

| Modus (`handover_mode`) | Ablauf | Geeignet, wenn … |
|---|---|---|
| `push` | WorkwearCenter sendet jeden Auftrag sofort per HTTPS-POST an eure Adresse. | die Auftragsverwaltung eine eigene Webadresse zum Empfangen hat. |
| `pull` | Die Auftragsverwaltung fragt regelmäßig `/api/v1/auftraege` ab und quittiert jeden übernommenen Auftrag. | die Auftragsverwaltung nicht von außen erreichbar ist (z. B. lokale Software im Büro). |
| `both` | beides | Umstellungsphase oder doppelte Absicherung. |
| `off` | Aufträge werden nur gesammelt. | Die Auftragsverwaltung ist noch nicht angebunden. |

Läuft die Auftragsverwaltung **auf demselben Server bzw. derselben Datenbank**, kann sie die Tabelle `yw_handover` auch direkt lesen. Der Weg über `pull` ist trotzdem empfohlen, weil die Quittierung dann sauber protokolliert wird.

## Einrichtung (config.php)

```php
'handover_mode'      => 'push',                     // off | push | pull | both
'handover_url'       => 'https://auftraege.example.de/workwearcenter/eingang',
'handover_secret'    => '…mindestens 32 zufällige Zeichen…',
'handover_api_token' => '…mindestens 32 zufällige Zeichen…',   // nur für pull/both
```

Schlüssel erzeugen: `php -r "echo bin2hex(random_bytes(32));"`

Danach im Admin unter **„Übergabe“** auf **„Verbindung testen“** klicken. Ältere, bereits freigegebene Aufträge (vor Version 10) lassen sich dort mit einem Klick nachträglich für die Übergabe vorbereiten.

Für zuverlässige, zeitnahe Übergaben sollte der Cronjob `cron.php` alle 5 Minuten laufen (er erledigt auch den Mailversand). Ohne Cron wird nach Seitenaufrufen nachgearbeitet.

## Push: Was eure Auftragsverwaltung empfängt

`POST` an `handover_url` mit JSON-Inhalt und diesen Headern:

| Header | Bedeutung |
|---|---|
| `X-WWC-Event` | `order.approved` (oder `test` beim Verbindungstest) |
| `X-WWC-Delivery` | eindeutige Kennung (32 Hex-Zeichen) – bei Wiederholungen identisch |
| `X-WWC-Timestamp` | Unix-Zeitstempel des Versands |
| `X-WWC-Signature` | `sha256=` + HMAC-SHA256 über `Timestamp + "." + Body` mit `handover_secret` |

**Pflichten des Empfängers:**
1. **Signatur prüfen** und Zeitstempel älter als 5 Minuten ablehnen. Nur so ist sicher, dass der Auftrag wirklich vom WorkwearCenter kommt und unterwegs nicht verändert wurde.
2. **Doppelte Zustellungen erkennen:** Dieselbe `delivery_id` darf nur einmal einen Auftrag anlegen. Wiederholungen passieren, wenn eine Antwort verloren geht.
3. **Mit HTTP 2xx antworten**, sobald der Auftrag sicher gespeichert ist. Optional die eigene Auftragsnummer zurückgeben:
   ```json
   {"reference": "AV-2026-00123"}
   ```
   Die Nummer erscheint dann im WorkwearCenter beim Auftrag.

Jede andere Antwort (oder keine Antwort innerhalb von 10 Sekunden) gilt als Fehler. Es folgen automatische Wiederholungen nach 1, 5, 15, 30, 60, 120, 240, 480, 720 und 1440 Minuten. Nach 10 Fehlversuchen wird der Auftrag als „Fehlgeschlagen“ markiert, alle Administratoren bekommen eine E-Mail, und im Admin lässt er sich per Knopfdruck erneut senden.

Ein vollständiger, lauffähiger Beispielempfänger in PHP liegt unter `tools/beispiel-empfaenger-auftragsverwaltung.php`. Er gehört nicht auf den WorkwearCenter-Server und ist dort gesperrt.

Signaturprüfung in anderen Sprachen (Pseudocode):
```
erwartet = "sha256=" + hex(hmac_sha256(secret, timestamp + "." + rohdaten_des_body))
gültig   = konstantzeit_vergleich(erwartet, header["X-WWC-Signature"]) und |jetzt - timestamp| <= 300
```

## Pull: Aufträge abholen

Alle Anfragen mit Header `Authorization: Bearer <handover_api_token>`, immer über HTTPS.

| Anfrage | Ergebnis |
|---|---|
| `GET /api/v1/auftraege` | noch nicht quittierte Aufträge (Standard: `status=offen`, max. 50, `limit` bis 200) |
| `GET /api/v1/auftraege?status=alle` bzw. `quittiert` | alle bzw. bereits übernommene |
| `GET /api/v1/auftraege/{order_id}` | ein einzelner Auftrag |
| `POST /api/v1/auftraege/{order_id}/quittung` mit `{"reference":"AV-2026-00123"}` | markiert den Auftrag als übernommen (Referenz optional) |

Ablauf: offene Aufträge holen → in der Auftragsverwaltung anlegen → jeden einzeln quittieren. Nicht quittierte Aufträge werden beim nächsten Abruf erneut geliefert. Auch hier anhand der `delivery_id` Doppelanlagen vermeiden.

Beispiel mit curl:
```
curl -H "Authorization: Bearer $TOKEN" https://workwearcenter.de/api/v1/auftraege
curl -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
     -d '{"reference":"AV-2026-00123"}' https://workwearcenter.de/api/v1/auftraege/123/quittung
```

Antwortcodes: `200` ok · `401` Schlüssel fehlt/falsch · `404` nicht gefunden bzw. Schnittstelle aus · `429` zu viele Fehlversuche (15 Minuten Sperre).

## Datensatz (Schema v1) – echtes Beispiel

Alle Beträge sind **ganze Cent** (Integer), Währung EUR. Zeitangaben nach ISO 8601 mit Zeitzone.

```json
{
  "schema": "workwearcenter.order.v1",
  "event": "order.approved",
  "delivery_id": "2336377f2418225b1e741682606a7c5b",
  "generated_at": "2026-09-26T22:42:14+02:00",
  "source": {
    "system": "WorkwearCenter",
    "version": "10.0.0",
    "url": "https://workwearcenter.de"
  },
  "order": {
    "id": 4,
    "number": "WWC-000004",
    "status": "approved",
    "created_at": "2026-09-26T22:42:13+02:00",
    "approved_at": "2026-09-26T22:42:14+02:00",
    "approved_by": {
      "id": 1,
      "name": "Maria Manager",
      "email": "manager@musterbau.de"
    },
    "approval_type": "manager",
    "note": "Bitte mit Namen",
    "currency": "EUR",
    "totals": {
      "net_cents": 8570,
      "gross_cents": 10198,
      "employee_share_cents": 1900,
      "company_share_cents": 8298,
      "shipping": {
        "net_cents": 590,
        "gross_cents": 702,
        "employee_cents": 0,
        "company_cents": 702
      }
    }
  },
  "customer": {
    "company_id": 1,
    "slug": "musterbau",
    "legal_name": "Musterbau GmbH",
    "shop_name": "Musterbau Mitarbeiter-Shop"
  },
  "employee": {
    "id": 2,
    "name": "Max Mitarbeiter",
    "email": "max@musterbau.de",
    "department": "Montage",
    "cost_center": "120",
    "location": "",
    "employee_number": ""
  },
  "delivery": {
    "mode": "delivery",
    "company": "Musterbau GmbH",
    "address_lines": [
      "Musterbau GmbH",
      "Industriestr. 1",
      "33154 Salzkotten"
    ]
  },
  "items": [
    {
      "line": 1,
      "product_id": 2,
      "sku": "JN002",
      "name": "Heavy Hoodie",
      "description": "Warmer Kapuzenpullover mit Kängurutasche.",
      "color": "Graphit",
      "size": "L",
      "quantity": 2,
      "vat_rate": 19,
      "unit_net_cents": 3990,
      "unit_gross_cents": 4748,
      "line_net_cents": 7980,
      "line_gross_cents": 9496,
      "employee_share_cents": 1900,
      "company_share_cents": 7596,
      "decorations": [
        {
          "position": "chest_left",
          "position_label": "Brust links",
          "method": "embroidery",
          "method_label": "Stick",
          "text": "Firmenlogo 8 cm",
          "motif_id": null,
          "motif_name": null
        }
      ],
      "base_decoration": {
        "type": "",
        "details": ""
      }
    }
  ]
}

```

Feldhinweise:
- `order.number`: sprechende Nummer `WWC-000123`. `order.id` ist die technische ID.
- `order.approval_type`: `manager` (durch Verantwortlichen) oder `automatic`.
- `delivery.mode`: `delivery` (an Firmenadresse, `address_lines`) oder `pickup` (Abholung bei Yanik Textildruck).
- `items[].decorations`: Veredelungspositionen mit maschinenlesbarem Code (`position`, `method`) und deutscher Bezeichnung (`position_label`, `method_label`), optional mit verknüpftem Motiv.
- `items[].employee_share_cents` / `company_share_cents`: Anteile für die **ganze Zeile**. `unit_*` gilt pro Stück.
- `order.totals.shipping.employee_cents` / `company_cents`: Wer den Versand trägt.
- Der Datensatz ist eine **Momentaufnahme zum Zeitpunkt der Freigabe**. Spätere Änderungen an Produkten oder Preisen verändern ihn nicht.

## Sicherheit

- Nur HTTPS (Ausnahme: `http://localhost` bei gleicher Maschine).
- Keine Weiterleitungen: Die `handover_url` muss direkt antworten.
- Schlüssel nie per E-Mail oder Chat weitergeben. Bei Verdacht sofort in beiden Systemen austauschen.
- Die Abruf-Schnittstelle sperrt nach 20 Fehlversuchen je IP für 15 Minuten.

## Ergänzung ab 10.6.0

Jede Veredelung (`decorations[]`) kann zusätzlich enthalten:

| Feld | Bedeutung |
|---|---|
| `motif_file` | vollständige Adresse der Logo-Datei (SVG oder PNG) oder `null` |
| `size` | Größenstufe `s` (klein), `m` (normal), `l` (groß) relativ zur Druckzone |

Alle bisherigen Felder bleiben unverändert.

## Ergänzung ab 10.11.0: Sammelbestellung und Größentausch

### Sammelbestellung (Bestellfenster)

Pro Firma lässt sich in der Kundenakte (Regeln & Budget → „Umtausch, Jahreswechsel & Sammelbestellung“) eine Sammelbestellung einstellen: monatlich zu einem Stichtag (1.–28.) oder alle 14 Tage ab einem Starttermin. Freigegebene Bestellungen erhalten dann wie bisher sofort ihren Übergabedatensatz (`order.approved`), werden aber erst **ab dem Stichtag 00:00 Uhr** gesendet bzw. abgerufen (`available_at`). Am Datensatz selbst ändert sich nichts.

- Push: Die Warteschlange sendet zurückgehaltene Aufträge beim ersten Cronlauf nach dem Stichtag.
- Pull: `GET /api/v1/auftraege?status=offen` liefert zurückgehaltene Aufträge erst ab dem Stichtag. `status=offen|alle|quittiert` liefern außerdem nur noch Bestellaufträge (`event = order.approved`), keine Tauschaufträge.
- Wird die Sammelbestellung geändert oder abgeschaltet, werden noch zurückgehaltene Aufträge neu terminiert bzw. sofort freigegeben.

### Größentausch: Datensatz `workwearcenter.exchange.v1`

Wird ein Antrag auf **Größentausch** freigegeben (durch Verantwortliche oder – ohne interne Freigabe – direkt), legt WorkwearCenter einen eigenen Übergabedatensatz an:

- Tabelle `yw_handover`, `event = exchange.approved:<Antrags-ID>` (je Antrag eindeutig; der Zusatz `:<ID>` wird beim Senden entfernt, `X-WWC-Event` lautet `exchange.approved`).
- `order_id` = ursprüngliche Bestellung.
- **Rückgaben** erzeugen keinen Übergabedatensatz (keine Produktion nötig); sie werden in der Verwaltung unter „Umtausch & Retouren“ bearbeitet.

**Versand je Format**

| `handover_format` | Verhalten |
|---|---|
| `workwearcenter` (eigenes Schema v1) | wird wie ein Auftrag per Push gesendet (gleiche Signatur `X-WWC-*`, `X-WWC-Event: exchange.approved`). Der Empfänger muss das `schema` prüfen und unbekannte Schemata mit 2xx annehmen oder mit 4xx ablehnen. |
| `auftragszentrale` | wird **nicht automatisch gesendet**. Der Datensatz hat den Status `manual` („Manuell übertragen“). Die Verwaltung überträgt den Tausch von Hand in die Auftragszentrale und markiert ihn unter „Umtausch & Retouren“ als übertragen (optional mit Auftragsnummer, landet in `external_reference`). |

Das Zielformat der Auftragszentrale für Tauschaufträge ist noch nicht festgelegt. Sobald die Auftragszentrale ein Ereignis dafür anbietet, wird hier eine Übersetzung analog zu `order.upsert` ergänzt; bis dahin bleibt es beim manuellen Weg, damit kein Tausch verloren geht oder als Neubestellung missverstanden wird.

**Beispiel (echter Datensatz aus dem Testlauf)**

```json
{
  "schema": "workwearcenter.exchange.v1",
  "event": "exchange.approved",
  "delivery_id": "4d588b11590d361a835ad861d038af8a",
  "generated_at": "2026-10-09T15:18:39+02:00",
  "source": { "system": "WorkwearCenter", "version": "10.11.0", "url": "https://workwearcenter.de" },
  "exchange": {
    "id": 7,
    "number": "WWC-000015-T7",
    "type": "exchange",
    "status": "approved",
    "reason": "fällt zu klein aus",
    "requested_at": "2026-10-09T15:18:39+02:00",
    "approved_at": "2026-10-09T15:18:39+02:00",
    "approved_by": { "id": 12, "name": "Vize" },
    "quantity": 1,
    "old_size": "M",
    "new_size": "L",
    "budget_delta_cents": 0
  },
  "order": { "id": 15, "number": "WWC-000015", "external_reference": null, "line": 1 },
  "customer": { "company_id": 5, "slug": "firma-a", "legal_name": "Firma-A GmbH", "shop_name": "Firma-A Shop" },
  "employee": { "id": 13, "name": "Mia", "email": "mia@a.de", "department": "Bau", "cost_center": "KST1", "location": "", "employee_number": "" },
  "delivery": { "mode": "delivery", "company": "Firma-A GmbH", "address_lines": ["Musterstr. 1", "33154 Salzkotten"] },
  "item": {
    "line": 1, "product_id": 3, "sku": "E2E-POLO", "name": "Polo E2E", "description": "Test", "color": "Navy",
    "size": "L", "original_size": "M", "quantity": 1, "vat_rate": 19,
    "unit_net_cents": 2000, "unit_gross_cents": 2380, "line_net_cents": 2000, "line_gross_cents": 2380,
    "employee_share_cents": 0, "company_share_cents": 2380,
    "decorations": [], "base_decoration": { "type": "", "details": "" }
  }
}
```

Feldhinweise:
- `exchange.number`: `WWC-<Bestellnummer>-T<Antrags-ID>`, eindeutig je Tausch.
- `item`: die ursprüngliche Position (Artikel, Veredelungen, Preise) – `size` ist die **neue** Größe, `original_size` die bisherige, `quantity` die getauschte Menge. Preise/Anteile in `item` beziehen sich auf die ursprüngliche Position; ab `line_*` gelten sie für die ganze ursprüngliche Zeile.
- `exchange.budget_delta_cents`: Preisdifferenz für das Mitarbeiterbudget (0 = gleicher Preis; positiv = Mehrkosten, z. B. Übergröße). Rein informativ – die Verrechnung erfolgt im WorkwearCenter.
- Erwartete Abwicklung: Ware in `original_size` zurücknehmen, gleiche Ware in `size` mit identischer Veredelung produzieren und an `delivery` liefern.
