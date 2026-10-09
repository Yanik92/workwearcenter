# Statusrückmeldung Auftragszentrale → WorkwearCenter

Gilt ab WorkwearCenter 10.3.0 · für den Chat, der die Auftragszentrale weiterentwickelt.

Damit Mitarbeitende im Firmenshop sehen, wie weit ihre Bestellung ist, meldet die Auftragszentrale Statusänderungen und Sendungsnummern an das WorkwearCenter zurück. Das WorkwearCenter zeigt dann:
- einen Fortschrittsbalken: Freigegeben → In Produktion → Fertig → Versendet bzw. Abgeholt,
- die Sendungsnummer mit Knopf „Sendung verfolgen“,
- eine E-Mail an die Mitarbeiterin bzw. den Mitarbeiter.

## Wann senden?

Bei jeder Änderung des Produktionsstands eines Auftrags, der **aus dem WorkwearCenter stammt** (Quelle „WorkwearCenter“, externe ID `WWC-…`). Und sobald ein Versandlabel erzeugt wurde, mit Sendungsnummer.

## Adresse und Signatur

`POST https://www.workwearcenter.de/api/v1/status`

Es gilt dieselbe Signatur wie bei der Übergabe in die Auftragszentrale, nur in umgekehrter Richtung. Der Schlüssel ist **derselbe Kanalschlüssel**, den die Auftragszentrale für den Kanal „WorkwearCenter“ erzeugt hat (im WorkwearCenter als `handover_secret` eingetragen).

| Header | Wert |
|---|---|
| `Content-Type` | `application/json` |
| `X-Yanik-Channel` | `auftragszentrale` |
| `X-Yanik-Time` | Unix-Zeit in Millisekunden (max. 5 Minuten Abweichung) |
| `X-Yanik-Nonce` | einmaliger Wert, `[A-Za-z0-9._:-]{16,120}` |
| `X-Yanik-Signature` | `hex(HMAC-SHA256(Schlüssel, Zeit + "\n" + Nonce + "\n" + "POST" + "\n" + "/api/v1/status" + "\n" + Body))` |

## Inhalt

Verbindungstest:
```json
{"event": "ping"}
```

Statusänderung:
```json
{
  "event": "order.status",
  "order": {
    "id": "WWC-000123",
    "externalReference": "YT-2026-00042",
    "status": "shipped",
    "tracking": { "carrier": "DHL", "number": "00340434161234567890", "url": "" },
    "updatedAt": "2026-10-02T14:05:00+02:00"
  }
}
```

- `id`: die externe ID aus dem Import (`channel_orders.external_id`), immer `WWC-` plus Nummer.
- `status`: genau einer dieser Werte:

| Wert | Anzeige im Shop | Vorschlag Auftragszentrale |
|---|---|---|
| `accepted` | In Produktion (Schritt 2) | Auftrag bestätigt / angenommen |
| `goods_ordered` | In Produktion | Ware bestellt (`production_status = bestellt`) |
| `goods_received` | In Produktion | Ware eingetroffen |
| `production` | In Produktion | `production_status = produktion` |
| `quality_check` | In Produktion | Qualitätskontrolle |
| `ready` | Fertig | `production_status = fertig` |
| `shipped` | Versendet | DHL-Label erzeugt bzw. übergeben |
| `picked_up` | Abgeholt | Kunde hat abgeholt |
| `completed` | Abgeschlossen | Auftrag abgeschlossen |

- `tracking`: optional. `carrier` z. B. `DHL`, `DPD`, `UPS`, `GLS` oder `Hermes`. Für diese baut das WorkwearCenter den Verfolgungslink selbst. `number` sind 4–80 Zeichen aus Buchstaben, Ziffern und Bindestrich. `url` ist optional und nur `https://` erlaubt.
- `externalReference`: optional, die YT-Nummer.

## Antworten

| Code | Bedeutung | Was tun |
|---|---|---|
| 200 `{"ok":true,"changed":true/false}` | übernommen (`changed=false`: war schon so) | fertig |
| 400 | Inhalt ungültig (Status, Nummer, JSON) | Fehler protokollieren, nicht wiederholen |
| 401 | Signatur falsch oder abgelaufen | Schlüssel bzw. Uhrzeit prüfen |
| 404 | Bestellung unbekannt oder Rückmeldung nicht eingerichtet | nicht wiederholen |
| 409 | Nonce doppelt, oder Bestellung nicht freigegeben bzw. zurückgezogen | nicht wiederholen |
| 429 / 5xx / keine Antwort | vorübergehend | später erneut senden (mit neuer Nonce) |

Mehrfaches Senden desselben Status ist unschädlich. E-Mails gehen nur bei einer echten Änderung raus.

## Beispiel (PHP, für die Auftragszentrale)

```php
function wwc_status(string $secret, string $wwcId, string $status, ?array $tracking = null): int {
    $body  = json_encode(['event' => 'order.status', 'order' => array_filter([
        'id' => $wwcId, 'status' => $status, 'tracking' => $tracking,
    ])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $time  = (string) (int) floor(microtime(true) * 1000);
    $nonce = bin2hex(random_bytes(16));
    $sig   = hash_hmac('sha256', $time . "\n" . $nonce . "\nPOST\n/api/v1/status\n" . $body, $secret);
    $ch = curl_init('https://www.workwearcenter.de/api/v1/status');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Yanik-Channel: auftragszentrale',
            'X-Yanik-Time: ' . $time, 'X-Yanik-Nonce: ' . $nonce, 'X-Yanik-Signature: ' . $sig]]);
    curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
    return $code;
}
```

Empfehlung: Rückmeldungen in der Auftragszentrale als eigene Warteschlange mit Wiederholungen umsetzen, damit ein kurzer Ausfall des Shops nichts verliert.
