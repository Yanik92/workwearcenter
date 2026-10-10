# WorkwearCenter API v1 – Dokumentation

Stand: Version 10.8.0 · Basis-URL: `https://workwearcenter.de/api/v1`

Diese Dokumentation ist für die Entwickler (bzw. die KI-Chats) der **Auftragszentrale**, von **Fan-Bande.de** und für Partner gedacht. Sie beschreibt alles, was ein fremdes System braucht, um Kunden, Produkte und Bestellungen des WorkwearCenters zu lesen und Produktionsstände zurückzumelden.

**Grundregel für alle Zeiten:** Felder und Endpunkte werden nur **ergänzt**, nie umbenannt oder entfernt. Ein Programm, das heute gegen diese API gebaut wird, läuft auch nach späteren Updates.

---

## 1. Überblick

| Was | Wie |
|---|---|
| Format | JSON (UTF-8), Antworten mit `Content-Type: application/json` |
| Verschlüsselung | **Nur HTTPS.** HTTP-Anfragen werden mit `400 https_required` abgelehnt. |
| Anmeldung | `Authorization: Bearer wwc_…` (API-Schlüssel, siehe Abschnitt 2) |
| Rechte | je Schlüssel: `orders:read`, `orders:write`, `customers:read`, `products:read` |
| Begrenzung | 600 Anfragen je 10 Minuten und Schlüssel (`429 rate_limited`, Kopfzeile `Retry-After`) |
| Zeitangaben | ISO 8601 mit Zeitzone, z. B. `2026-10-07T09:54:15+02:00` |
| Beträge | immer **Cent als ganze Zahl**, netto (`…_cents`); Währung `EUR` |
| IDs | Bestellungen können per Zahl (`8`) oder Nummer (`WWC-000008`) angesprochen werden |

### Richtung der Daten

```
Auftragszentrale / Fan-Bande / Partner         WorkwearCenter
          │   GET  kunden, produkte, bestellungen   │
          │ ───────────────────────────────────────▶│   lesen
          │   POST bestellungen/{id}/status          │
          │ ───────────────────────────────────────▶│   Produktionsstand + Sendungsnummer zurückmelden
          │   POST bestellungen/{id}/quittung        │
          │ ───────────────────────────────────────▶│   "Auftrag bei uns angekommen" bestätigen
          │                                          │
          │◀─────────────────────────────────────── │   optional: Push `order.upsert` (Webhook, Abschnitt 7)
```

---

## 2. API-Schlüssel

Schlüssel werden im WorkwearCenter unter **Verwaltung → Einstellungen → System → Schnittstellen → Zugänge für andere Systeme** angelegt. Pro angebundenem System ein eigener Schlüssel (Auftragszentrale, Fan-Bande, Partner, Test).

* Der Schlüssel sieht so aus: `wwc_` + 48 Hex-Zeichen, z. B. `wwc_3f9a…` (52 Zeichen).
* Er wird **genau einmal** angezeigt. Das WorkwearCenter speichert nur einen SHA-256-Hash. Verloren = neuen Schlüssel anlegen, alten löschen.
* Jeder Schlüssel hat feste **Rechte** (Scopes) und optional eine **IP-Freigabe** (Komma-Liste). Anfragen von anderen IPs werden mit `403 ip_not_allowed` abgewiesen und protokolliert.
* Schlüssel können jederzeit **gesperrt** oder **gelöscht** werden; der Zugriff endet sofort.
* Letzte Nutzung und IP werden je Schlüssel angezeigt.

Verwendung:

```http
GET /api/v1/ping HTTP/1.1
Host: workwearcenter.de
Authorization: Bearer wwc_3f9a0c2b…
```

Empfohlene Rechte je System:

| System | Rechte |
|---|---|
| Auftragszentrale | `orders:read`, `orders:write`, `customers:read`, `products:read` |
| Fan-Bande.de | `products:read` (nur, wenn Fan-Bande tatsächlich Artikel aus dem WorkwearCenter zeigen soll – sonst gar keinen Schlüssel) |
| Partner / Auswertung | `orders:read` oder `customers:read` – so wenig wie möglich |

---

## 3. Fehler

Jede Fehlerantwort hat die Form `{"error": "<code>", "message": "<Erklärung auf Deutsch>"}`.

| HTTP | `error` | Bedeutung |
|---|---|---|
| 400 | `https_required` | Anfrage kam über HTTP statt HTTPS |
| 400 | `invalid_json` | Request-Body ist kein gültiges JSON |
| 400 | `invalid_status` | Produktionsstand unbekannt (Antwort enthält `allowed`) |
| 400 | `invalid_tracking_number` | Sendungsnummer enthält unzulässige Zeichen |
| 400 | `invalid_filter` / `invalid_since` | Filterparameter ungültig |
| 401 | `unauthorized` | Kein, ungültiger oder gesperrter Schlüssel (Kopfzeile `WWW-Authenticate: Bearer`) |
| 403 | `forbidden` | Schlüssel hat das nötige Recht nicht |
| 403 | `ip_not_allowed` | IP nicht in der Freigabeliste des Schlüssels |
| 404 | `not_found` | Datensatz oder Endpunkt existiert nicht |
| 429 | `too_many_requests` | zu viele fehlgeschlagene Anmeldungen von dieser IP (15 Minuten warten) |
| 429 | `rate_limited` | 600 Anfragen je 10 Minuten überschritten |

---

## 4. Endpunkte

### 4.1 Verbindung prüfen

`GET /api/v1/ping` – funktioniert **mit und ohne** Schlüssel. Mit Schlüssel zeigt die Antwort Name, System und Rechte; die Programmversion steht nur mit gültigem Schlüssel in `version` (ohne Schlüssel `null`).

```json
{
  "ok": true,
  "system": "WorkwearCenter",
  "version": "10.8.0",
  "api": "v1",
  "time": "2026-10-07T09:54:15+02:00",
  "key": { "name": "Auftragszentrale", "system": "auftragszentrale",
           "scopes": ["orders:read", "orders:write", "customers:read", "products:read"] }
}
```

### 4.2 Kunden (Firmen) · Recht `customers:read`

`GET /api/v1/kunden`

| Parameter | Bedeutung |
|---|---|
| `seit` | nur Firmen, die seit diesem Datum angelegt wurden (ISO 8601) |
| `aktiv=1` | nur freigeschaltete Firmenshops |
| `limit` | 1–200, Standard 100 |

```json
{
  "count": 1,
  "data": [{
    "id": 1,
    "slug": "musterbau",
    "legalName": "Musterbau GmbH",
    "shopName": "Musterbau Mitarbeiter-Shop",
    "active": true,
    "shopUrl": "https://workwearcenter.de/musterbau",
    "delivery": { "mode": "company", "address": "Musterbau GmbH\nIndustriestr. 1\n33154 Salzkotten" },
    "financing": { "mode": "company", "employeeSharePercent": 0 },
    "approvalRequired": true,
    "createdAt": "2026-09-26T22:23:24+02:00"
  }]
}
```

`delivery.mode`: `company` (Sammellieferung an die Firmenadresse) oder `pickup` (Abholung, keine Versandkosten).
`financing.mode`: `company` (Firma zahlt alles) oder `percentage` (Mitarbeitende tragen `employeeSharePercent` % selbst).

`GET /api/v1/kunden/{id}` – wie oben, zusätzlich `employeeCount` (freigegebene Mitarbeiterkonten) und `collection` (die für diese Firma freigeschaltete Kollektion, jedes Element im Produktformat aus 4.3).

### 4.3 Produkte · Recht `products:read`

`GET /api/v1/produkte` (`aktiv=1`, `limit` 1–500, Standard 200) und `GET /api/v1/produkte/{id}`

```json
{
  "id": 2,
  "sku": "JN002",
  "name": "Heavy Hoodie",
  "brand": "James & Nicholson",
  "supplier": "L-Shop",
  "category": "Hoodies",
  "color": "Graphit",
  "sizes": ["S", "M", "L", "XL"],
  "netPriceCents": 3990,
  "vatRate": 19,
  "decorationType": "none",
  "active": true,
  "images": {
    "front": "https://workwearcenter.de/assets/media/products/workwear-hoodie.webp",
    "back": "https://workwearcenter.de/uploads/products/product-d2dc8d9a96278ce9.png"
  }
}
```

`images` enthält nur die Ansichten, die gepflegt sind (`front`, `back`, `sleeve`). Die URLs sind absolut und öffentlich abrufbar. **Einkaufspreise werden nie ausgegeben.**

### 4.4 Bestellungen · Recht `orders:read`

`GET /api/v1/bestellungen`

| Parameter | Werte | Standard |
|---|---|---|
| `freigabe` | `approved`, `pending`, `rejected`, `cancelled`, `alle` | `approved` (nur freigegebene = produktionsreife Bestellungen) |
| `produktion` | `new`, `accepted`, `goods_ordered`, `goods_received`, `processing`, `production`, `quality_check`, `ready`, `shipped`, `picked_up`, `completed` | – |
| `kunde` | Firmen-ID | – |
| `seit` | ISO-Datum: alles, was seitdem angelegt, entschieden oder im Produktionsstand geändert wurde | – |
| `nach` | letzte bekannte Bestell-ID (Blättern) | – |
| `limit` | 1–100 | 50 |

Die Antwort enthält `count`, `data` und `next` (fertiger Pfad für die nächste Seite, `null` wenn leer).

**Empfohlene Abholung (Auftragszentrale):** alle paar Minuten `GET /api/v1/bestellungen?seit=<Zeitpunkt des letzten Laufs>` – das liefert neue Freigaben **und** Bestellungen, deren Stand sich geändert hat. Alternativ `nach=<letzte ID>` zum lückenlosen Durchblättern.

`GET /api/v1/bestellungen/{id}` bzw. `/bestellungen/WWC-000008`

Der Datensatz ist exakt das Schema `workwearcenter.order.v1`, das auch der Push (Abschnitt 7) sendet – ergänzt um die aktuellen Produktionsfelder:

```json
{
  "schema": "workwearcenter.order.v1",
  "event": "order.snapshot",
  "generated_at": "2026-10-07T09:54:15+02:00",
  "source": { "system": "WorkwearCenter", "version": "10.8.0", "url": "https://workwearcenter.de" },
  "order": {
    "id": 8,
    "number": "WWC-000008",
    "status": "approved",
    "created_at": "2026-09-26T23:05:50+02:00",
    "approved_at": "2026-09-26T23:05:51+02:00",
    "approved_by": { "id": 1, "name": "Maria Manager", "email": "manager@musterbau.de" },
    "approval_type": "manager",
    "note": "Bitte bis Monatsende",
    "currency": "EUR",
    "totals": {
      "net_cents": 16540, "gross_cents": 19682,
      "employee_share_cents": 3798, "company_share_cents": 15884,
      "shipping": { "net_cents": 590, "gross_cents": 702, "employee_cents": 0, "company_cents": 702 }
    },
    "approvalStatus": "approved",
    "productionStatus": "shipped",
    "productionUpdatedAt": "2026-10-07T09:50:07+02:00",
    "tracking": { "carrier": "DHL", "number": "123456", "url": "https://www.dhl.de/…?piececode=123456" },
    "externalReference": "YT-2026-00002"
  },
  "customer": { "company_id": 1, "slug": "musterbau", "legal_name": "Musterbau GmbH", "shop_name": "Musterbau Mitarbeiter-Shop" },
  "employee": { "id": 2, "name": "Max Mitarbeiter", "email": "max@musterbau.de", "department": "Montage",
                "cost_center": "120", "location": "", "employee_number": "" },
  "delivery": { "mode": "delivery", "company": "Musterbau GmbH",
                "address_lines": ["Musterbau GmbH", "Industriestr. 1", "33154 Salzkotten"] },
  "items": [{
    "line": 1,
    "product_id": 2, "sku": "JN002", "name": "Heavy Hoodie",
    "description": "Warmer Kapuzenpullover mit Kängurutasche.",
    "color": "Graphit", "size": "XL", "quantity": 3, "vat_rate": 19,
    "unit_net_cents": 3990, "unit_gross_cents": 4748,
    "line_net_cents": 11970, "line_gross_cents": 14244,
    "employee_share_cents": 2850, "company_share_cents": 11394,
    "decorations": [{
      "position": "chest_left", "position_label": "Brust links",
      "method": "embroidery", "method_label": "Stick",
      "text": "Firmenlogo 8 cm",
      "motif_id": 3, "motif_name": "Logo Musterbau",
      "motif_file": "https://workwearcenter.de/uploads/motifs/motif-ab12.svg",
      "size": "m"
    }],
    "base_decoration": { "type": "", "details": "" }
  }]
}
```

Wichtige Felder:

* `order.status` / `order.approvalStatus` – Freigabestand: `pending`, `approved`, `rejected`, `cancelled`.
* `order.productionStatus` – Produktionsstand (siehe 4.5), `new` solange nichts gemeldet wurde.
* `order.externalReference` – eure eigene Auftragsnummer, sobald per `status` oder `quittung` gemeldet.
* `items[].decorations[]` – Veredelung je Position: `position` (`chest_left`, `chest_right`, `back_top`, `back_large`, `sleeve_left`, `sleeve_right`, `neck`, `other`), `method` (`print`, `dtf`, `flex`, `embroidery`, `other`), `size` (`s` klein, `m` normal, `l` groß), `motif_file` (Logo-Datei als SVG/PNG, abrufbar ohne Anmeldung).
* `employee.cost_center` – Kostenstelle für die Rechnung an die Firma.

### 4.5 Produktionsstand melden · Recht `orders:write`

`POST /api/v1/bestellungen/{id}/status`

```json
{
  "status": "shipped",
  "tracking": { "carrier": "DHL", "number": "00340434161094000001", "url": "https://…" },
  "externalReference": "YT-2026-00099"
}
```

| Feld | Pflicht | Regeln |
|---|---|---|
| `status` | ja | einer von `accepted`, `goods_ordered`, `goods_received`, `production`, `quality_check`, `ready`, `shipped`, `picked_up`, `completed` |
| `tracking.carrier` | nein | max. 40 Zeichen (DHL, DPD, GLS, UPS, Hermes …) |
| `tracking.number` | nein | 4–80 Zeichen, nur Buchstaben, Ziffern, Bindestrich |
| `tracking.url` | nein | nur `https://`; fehlt sie, bildet das WorkwearCenter den Link für bekannte Paketdienste selbst |
| `externalReference` | nein | eure Auftragsnummer, max. 120 Zeichen |

Antwort: `{"ok": true, "order": "WWC-000008", "status": "shipped", "changed": true}` – `changed` ist `false`, wenn Stand und Sendungsnummer bereits so hinterlegt waren (Meldungen dürfen also beliebig wiederholt werden).

Was im WorkwearCenter passiert: Der Stand erscheint in der Verwaltung und im Firmenshop; bei `shipped`, `ready`, `picked_up` und `completed` werden Besteller und Firmen-Manager per E-Mail informiert, bei `shipped` mit Sendungslink. Jede Meldung wird im Verlauf protokolliert (`api:<system>`).

### 4.6 Eingang bestätigen · Recht `orders:write`

`POST /api/v1/bestellungen/{id}/quittung` · Body optional: `{"reference": "YT-2026-00099"}`

Markiert die Übergabe als angekommen (die Verwaltung sieht „bestätigt“ statt „offen“) und speichert eure Referenz. Antwort: `{"ok": true, "order": "WWC-000008", "externalReference": "YT-2026-00099"}`. Nur für freigegebene Bestellungen; sonst `404`.

---

## 5. Beispiele

**curl**

```bash
KEY="wwc_…"
curl -s -H "Authorization: Bearer $KEY" "https://workwearcenter.de/api/v1/bestellungen?seit=2026-10-01T00:00:00%2B02:00"
curl -s -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
     -d '{"status":"production","externalReference":"YT-2026-00099"}' \
     https://workwearcenter.de/api/v1/bestellungen/WWC-000008/status
```

**PHP**

```php
function wwc(string $method, string $path, ?array $body = null): array {
    $ch = curl_init('https://workwearcenter.de/api/v1' . $path);
    $headers = ['Authorization: Bearer ' . WWC_API_KEY, 'Accept: application/json'];
    if ($body !== null) { $headers[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    $data = json_decode((string)$raw, true) ?? [];
    if ($code >= 400) throw new RuntimeException('WorkwearCenter ' . $code . ': ' . ($data['message'] ?? $data['error'] ?? 'Fehler'));
    return $data;
}
// neue und geänderte Bestellungen seit dem letzten Lauf
$seite = wwc('GET', '/bestellungen?seit=' . rawurlencode($letzterLauf));
foreach ($seite['data'] as $bestellung) { /* anlegen oder aktualisieren */ }
wwc('POST', '/bestellungen/8/status', ['status' => 'accepted', 'externalReference' => 'YT-2026-00099']);
```

**Python**

```python
import requests
H = {"Authorization": "Bearer wwc_…"}
r = requests.get("https://workwearcenter.de/api/v1/bestellungen", params={"seit": "2026-10-01T00:00:00+02:00"}, headers=H, timeout=20)
r.raise_for_status()
for b in r.json()["data"]:
    print(b["order"]["number"], b["customer"]["legal_name"], b["order"]["productionStatus"])
```

---

## 6. Empfohlener Ablauf für die Auftragszentrale

1. Schlüssel mit allen vier Rechten anlegen, in der Auftragszentrale hinterlegen, `GET /ping` prüfen.
2. Alle 2–5 Minuten `GET /bestellungen?seit=<letzter Lauf − 1 Minute>`; `order.number` ist der eindeutige Schlüssel (Upsert).
3. Sobald ein Auftrag angelegt ist: `POST /bestellungen/{id}/quittung` mit eurer Auftragsnummer.
4. Bei jedem Statuswechsel in der Produktion: `POST /bestellungen/{id}/status` – beim Versand mit Sendungsnummer.
5. Firmen- und Produktdaten bei Bedarf über `GET /kunden/{id}` (Lieferadresse, Finanzierung, Kollektion) nachladen.

Hinweis: Mitarbeitende sehen in der Auftragszentrale **keine Preise** – die Preisfelder nur in Rechnungs-/Kalkulationsrollen anzeigen.

---

## 7. Ausgehender Push (Webhook) – optional

Zusätzlich zur Abholung kann das WorkwearCenter jede Freigabe sofort an eine URL senden (Verwaltung → System → Übergabe). Das ist der bisherige Weg zur Auftragszentrale und bleibt unverändert.

* `POST <eure URL>` mit Body = Datensatz aus 4.4 (Schema `workwearcenter.order.v1`), `event` = `order.upsert` bzw. `order.approved`, `delivery_id` eindeutig je Zustellversuch.
* Signatur per HMAC-SHA256 über `Zeit.Nonce.Body` mit dem gemeinsamen Geheimnis; Kopfzeilen `X-Yanik-Channel: workwearcenter`, `X-Yanik-Time`, `X-Yanik-Nonce`, `X-Yanik-Signature`. Zeitfenster 5 Minuten, Nonce wird gegen Wiederholung geprüft.
* Antwort `2xx` = zugestellt; sonst wird mit Abstand erneut versucht und die Verwaltung zeigt den Fehler.

Details, Einrichtung und das Pull-Verfahren mit dem älteren Abruf-Token stehen in `SCHNITTSTELLE-AUFTRAGSVERWALTUNG.md`; die signierte Statusrückmeldung in `STATUS-RUECKMELDUNG-AN-WORKWEARCENTER.md`. Beide alten Wege (`/api/v1/auftraege` mit `handover_api_token`, `/api/v1/status` mit X-Yanik-Signatur) funktionieren weiter – neue Anbindungen sollen aber die API-Schlüssel benutzen.

---

## 8. Sicherheit – was die API tut und was ihr tun müsst

Das WorkwearCenter:

* speichert Schlüssel nur als SHA-256-Hash; Vergleich serverseitig, Klartext nie in Logs;
* sperrt eine IP nach 20 ungültigen Schlüsseln für 15 Minuten;
* verlangt HTTPS, setzt `Cache-Control: no-store` und `X-Robots-Tag: noindex` auf alle API-Antworten;
* bindet jede Schreibaktion an den Schlüssel (`api:<system>` im Verlauf und Audit-Protokoll);
* prüft jede Eingabe (Status-Liste, Sendungsnummer-Format, nur-HTTPS-Links, Längen) und gibt nie Einkaufspreise, Passwörter, Hashes oder Admin-Daten aus;
* gibt für Fehler keine internen Details (Stacktraces, SQL) preis.

Ihr:

* bewahrt den Schlüssel wie ein Passwort auf (Konfigurationsdatei außerhalb des Webroots, nie im Code-Repository, nie im Chat);
* hinterlegt, wenn euer Server eine feste IP hat, diese als IP-Freigabe am Schlüssel;
* benutzt für jedes System einen eigenen Schlüssel und nur die Rechte, die es braucht;
* prüft beim Webhook die Signatur und lehnt alte Zeitstempel ab;
* behandelt alle Daten als personenbezogen (Namen, E-Mail-Adressen der Mitarbeitenden) – Zugriff nur für berechtigte Rollen, Löschfristen beachten.

---

## 9. Änderungsverlauf

| Version | Änderung |
|---|---|
| 10.8.0 | API v1 mit Schlüsseln, Rechten, IP-Freigabe und Drosselung; Endpunkte `ping`, `kunden`, `produkte`, `bestellungen`, `status`, `quittung`; OpenAPI-Datei `openapi.yaml` |
| 10.6.0 | `decorations[].motif_file` und `decorations[].size` im Bestelldatensatz |
| 10.3.0 | Statusrückmeldung mit Sendungsnummer (`/api/v1/status`, signiert) |
