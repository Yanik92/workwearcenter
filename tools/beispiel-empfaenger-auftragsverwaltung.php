<?php
/**
 * BEISPIEL: Empfang von WorkwearCenter-Aufträgen in einer Auftragsverwaltung (Push-Modus).
 *
 * Diese Datei gehört NICHT auf den WorkwearCenter-Server, sondern dient als Vorlage
 * für die Auftragsverwaltung. Sie zeigt die drei Pflichtschritte:
 *   1. Signatur prüfen (Echtheit + Unverändertheit)
 *   2. Doppelte Zustellungen erkennen (delivery_id)
 *   3. Mit HTTP 2xx antworten, optional mit eigener Auftragsnummer als "reference"
 *
 * Solange die Antwort nicht 2xx ist, wiederholt WorkwearCenter die Zustellung
 * automatisch (bis zu 10-mal über ca. 2 Tage).
 */
declare(strict_types=1);

$secret = getenv('WWC_HANDOVER_SECRET') ?: 'HIER-DEN-GLEICHEN-SCHLUESSEL-WIE-IN-WORKWEARCENTER-EINTRAGEN';
$storage = getenv('WWC_RECEIVER_DIR') ?: __DIR__ . '/eingang';

header('Content-Type: application/json; charset=utf-8');
if (strlen($secret) < 32 || str_starts_with($secret, 'HIER-')) { http_response_code(503); exit('{"error":"secret_not_configured"}'); }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit('{"error":"method"}'); }

$body      = (string)file_get_contents('php://input');
$timestamp = (string)($_SERVER['HTTP_X_WWC_TIMESTAMP'] ?? '');
$signature = (string)($_SERVER['HTTP_X_WWC_SIGNATURE'] ?? '');
$delivery  = (string)($_SERVER['HTTP_X_WWC_DELIVERY'] ?? '');
$event     = (string)($_SERVER['HTTP_X_WWC_EVENT'] ?? '');

// 1. Signatur prüfen und alte Nachrichten (Wiedereinspielung) ablehnen
$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $secret);
if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300 || !hash_equals($expected, $signature)) {
    http_response_code(401); exit('{"error":"invalid_signature"}');
}
if ($event === 'test') { exit('{"ok":true,"message":"Test empfangen"}'); }

$data = json_decode($body, true);
if (!is_array($data) || ($data['schema'] ?? '') !== 'workwearcenter.order.v1' || !preg_match('/^[a-f0-9]{32}$/', $delivery)) {
    http_response_code(400); exit('{"error":"invalid_payload"}');
}

// 2. Doppelte Zustellung erkennen – dieselbe delivery_id nie zweimal anlegen
if (!is_dir($storage)) mkdir($storage, 0700, true);
$file = $storage . '/' . $delivery . '.json';
if (is_file($file)) {
    $known = json_decode((string)file_get_contents($file), true);
    exit(json_encode(['ok' => true, 'reference' => $known['_reference'] ?? null, 'duplicate' => true]));
}

// 3. Hier den Auftrag in der eigenen Auftragsverwaltung anlegen.
//    Beispiel: eigene Auftragsnummer vergeben und zurückmelden.
$reference = 'AV-' . date('Y') . '-' . str_pad((string)$data['order']['id'], 5, '0', STR_PAD_LEFT);
$data['_reference'] = $reference;
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

echo json_encode(['ok' => true, 'reference' => $reference]);
