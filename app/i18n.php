<?php
declare(strict_types=1);

/*
 * 10.12: Mehrsprachiger Firmenshop.
 *
 * Texte liegen als PHP-Arrays je Sprache in app/lang/<code>.php (Schlüssel => Text).
 * Fehlt ein Schlüssel, gilt Deutsch. Platzhalter stehen in geschweiften Klammern ({name}).
 * Pluralformen sind Listen: [eins, andere] bzw. für Polnisch/Russisch/Rumänisch [eins, wenige, viele].
 *
 * Die aktuelle Sprache ist pro Anfrage global und standardmäßig Deutsch. Nur der Firmenshop
 * stellt sie um (yw_shop_lang_init); Website und Verwaltung bleiben dadurch automatisch deutsch.
 * Mails an Mitarbeitende werden mit yw_with_lang() in der Sprache der Empfängerin erstellt.
 */

const YW_LANGS = ['de'=>'Deutsch','en'=>'English','pl'=>'Polski','tr'=>'Türkçe','ru'=>'Русский','ro'=>'Română'];
const YW_LANG_COOKIE = 'yw_lang';

function yw_lang_valid(?string $code): bool { return is_string($code) && isset(YW_LANGS[$code]); }

function yw_lang(): string { return (string)($GLOBALS['yw_lang'] ?? 'de'); }

function yw_set_lang(?string $code): void { $GLOBALS['yw_lang'] = yw_lang_valid($code) ? $code : 'de'; }

/** Führt $fn in einer anderen Sprache aus (z. B. Mail an eine Person) und stellt danach zurück. */
function yw_with_lang(?string $code, callable $fn): mixed {
    $previous = yw_lang();
    yw_set_lang($code);
    try { return $fn(); }
    finally { $GLOBALS['yw_lang'] = $previous; }
}

/** Übersetzungstabelle einer Sprache (einmal je Anfrage geladen). */
function yw_lang_table(string $code): array {
    static $tables = [];
    if (!yw_lang_valid($code)) $code = 'de';
    if (!isset($tables[$code])) {
        $file = __DIR__.'/lang/'.$code.'.php';
        $data = is_file($file) ? require $file : [];
        $tables[$code] = is_array($data) ? $data : [];
    }
    return $tables[$code];
}

/** Index der Pluralform nach den Regeln der Sprache (CLDR, vereinfacht für ganze Zahlen). */
function yw_plural_index(string $lang, int $n): int {
    $n = abs($n); $mod10 = $n % 10; $mod100 = $n % 100;
    return match ($lang) {
        'pl' => $n === 1 ? 0 : (($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? 1 : 2),
        'ru' => ($mod10 === 1 && $mod100 !== 11) ? 0 : (($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) ? 1 : 2),
        'ro' => $n === 1 ? 0 : (($n === 0 || ($mod100 >= 1 && $mod100 <= 19)) ? 1 : 2),
        default => $n === 1 ? 0 : 1,
    };
}

/** Rohwert eines Schlüssels (Text oder Pluralliste) in der aktuellen Sprache, sonst Deutsch. */
function yw_lang_raw(string $key, ?string $lang = null): mixed {
    $lang = $lang ?? yw_lang();
    $table = yw_lang_table($lang);
    if (array_key_exists($key, $table)) return $table[$key];
    $de = yw_lang_table('de');
    return $de[$key] ?? $key;
}

/**
 * Übersetzt einen Schlüssel. Platzhalter: t('bestellung.nr', ['id'=>12]) ersetzt {id}.
 * Bei Pluraltexten bestimmt $params['n'] die Form.
 */
function t(string $key, array $params = []): string {
    $lang = yw_lang();
    $text = yw_lang_raw($key, $lang);
    if (is_array($text)) {
        $forms = array_values($text);
        $index = yw_plural_index($lang, (int)($params['n'] ?? 0));
        $text = (string)($forms[min($index, count($forms) - 1)] ?? '');
    }
    if (!$params) return (string)$text;
    $replace = [];
    foreach ($params as $name => $value) $replace['{'.$name.'}'] = (string)$value;
    return strtr((string)$text, $replace);
}

/** Plural-Kurzform: tn('x.artikel', 3) = t('x.artikel', ['n'=>3]). */
function tn(string $key, int $n, array $params = []): string { return t($key, ['n' => $n] + $params); }

/* ---------- Zahlen, Beträge, Datum ---------- */

/** Zahl mit Trennzeichen der Sprache. */
function yw_number(float $value, int $decimals = 0): string {
    return number_format($value, $decimals, t('fmt.decimal'), t('fmt.thousands'));
}

/** Eurobetrag im Format der aktuellen Sprache (Beträge bleiben immer Euro). */
function yw_money(int $cents): string {
    $negative = $cents < 0;
    $amount = yw_number(abs($cents) / 100, 2);
    return ($negative ? '-' : '').str_replace('{n}', $amount, t('fmt.money'));
}

/** Prozentwert (z. B. 30 % / 30% / %30), ohne überflüssige Nachkommastellen. */
function yw_percent(float $value): string {
    $text = rtrim(rtrim(yw_number($value, 2), '0'), t('fmt.decimal'));
    return str_replace('{n}', $text, t('fmt.percent'));
}

/** Datum im Format der Sprache. $style: date, datetime, short (Tag + Monat). */
function yw_date(mixed $value, string $style = 'date'): string {
    if ($value instanceof DateTimeInterface) $ts = $value->getTimestamp();
    elseif (is_int($value)) $ts = $value;
    else { $ts = strtotime((string)$value); if ($ts === false) return ''; }
    return date(t('fmt.'.$style), $ts);
}

/** Monatsname + Jahr (z. B. „Oktober 2026“, „październik 2026“). */
function yw_month_label(string $ym): string {
    if (!preg_match('/^(\d{4})-(\d{2})$/D', $ym, $m)) return $ym;
    $months = yw_lang_raw('fmt.months');
    $name = is_array($months) ? (string)($months[(int)$m[2] - 1] ?? $m[2]) : $m[2];
    return $name.' '.$m[1];
}

/** Angaben für shop.js (Zahlenformat und Texte), als JSON im data-Attribut des Seitenkopfs. */
function yw_js_i18n(): string {
    $keys = ['js.item','js.size','js.each_net','js.line_total','js.your_share','js.change','js.change_label','js.remove','js.remove_label','js.removed','js.limit_reached','js.added','js.added_size','js.selected','js.sending'];
    $texts = [];
    foreach ($keys as $key) $texts[substr($key, 3)] = yw_lang_raw($key);
    return (string)json_encode([
        'lang' => yw_lang(),
        'dec' => t('fmt.decimal'),
        'thou' => t('fmt.thousands'),
        'money' => t('fmt.money'),
        'plural' => yw_lang(),
        't' => $texts,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/* ---------- Sprachwahl ---------- */

/** Für eine Firma angebotene Sprachen (Deutsch immer, Reihenfolge wie YW_LANGS). */
function yw_company_langs(array $company): array {
    $raw = trim((string)($company['shop_languages'] ?? ''));
    if ($raw === '') return array_keys(YW_LANGS);
    $wanted = array_flip(array_filter(array_map('trim', explode(',', $raw)), 'yw_lang_valid'));
    $wanted['de'] = true;
    return array_values(array_filter(array_keys(YW_LANGS), fn($code) => isset($wanted[$code])));
}

/** Speicherwert für companies.shop_languages aus einer Auswahl (NULL = alle). */
function yw_company_langs_value(array $codes): ?string {
    $codes = array_values(array_unique(array_filter(array_map('strval', $codes), 'yw_lang_valid')));
    $codes[] = 'de';
    $ordered = array_values(array_filter(array_keys(YW_LANGS), fn($code) => in_array($code, $codes, true)));
    return count($ordered) === count(YW_LANGS) ? null : implode(',', $ordered);
}

/** Erste passende Sprache aus einem Accept-Language-Kopf (nach Gewichtung), sonst null. */
function yw_lang_from_header(string $header, array $allowed): ?string {
    $candidates = [];
    foreach (explode(',', $header) as $i => $part) {
        $bits = explode(';', trim($part));
        $code = strtolower(substr(trim($bits[0]), 0, 2));
        $q = 1.0;
        foreach (array_slice($bits, 1) as $bit) if (preg_match('/^\s*q=([01](?:\.\d{1,3})?)\s*$/', $bit, $m)) $q = (float)$m[1];
        if ($code !== '' && $q > 0) $candidates[] = [$q, -$i, $code];
    }
    rsort($candidates);
    foreach ($candidates as [, , $code]) if (in_array($code, $allowed, true)) return $code;
    return null;
}

/**
 * Sprache im Firmenshop festlegen: Konto → Cookie → Browser (Accept-Language) → Deutsch,
 * jeweils nur aus den Sprachen, die die Firma anbietet.
 */
function yw_shop_lang_init(array $company, ?array $account = null): string {
    $allowed = yw_company_langs($company);
    $pick = null;
    if ($account && in_array((string)($account['language'] ?? ''), $allowed, true)) $pick = (string)$account['language'];
    if ($pick === null && in_array((string)($_COOKIE[YW_LANG_COOKIE] ?? ''), $allowed, true)) $pick = (string)$_COOKIE[YW_LANG_COOKIE];
    if ($pick === null) $pick = yw_lang_from_header((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), $allowed);
    yw_set_lang($pick ?? 'de');
    $GLOBALS['yw_lang_shop'] = true;
    return yw_lang();
}

/** Läuft die Anfrage im Firmenshop (Sprachwahl aktiv)? Dann bekommt der Seitenkopf die JS-Texte. */
function yw_lang_shop_active(): bool { return !empty($GLOBALS['yw_lang_shop']); }

/** Sprache für Mails an ein Konto (gespeicherte Wahl, sofern die Firma sie anbietet). */
function yw_account_lang(?array $account, ?array $company = null): string {
    $code = (string)($account['language'] ?? '');
    if (!yw_lang_valid($code)) return 'de';
    if ($company !== null && !in_array($code, yw_company_langs($company), true)) return 'de';
    return $code;
}

/** Sprache eines Kontos per ID (für Mails aus Verwaltung, Cron und Schnittstelle). */
function yw_account_lang_by_id(int $accountId): string {
    try {
        $q = db()->prepare('SELECT ea.language,c.shop_languages FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.id=? LIMIT 1');
        $q->execute([$accountId]);
        $row = $q->fetch();
        return $row ? yw_account_lang(['language' => $row['language']], ['shop_languages' => $row['shop_languages']]) : 'de';
    } catch (Throwable $e) { return 'de'; }
}

function yw_lang_cookie_set(string $code): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie(YW_LANG_COOKIE, $code, ['expires' => time() + 365 * 86400, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE[YW_LANG_COOKIE] = $code;
}

/** Kleiner Sprachumschalter (Formular ohne JavaScript; leer, wenn die Firma nur Deutsch anbietet). */
function yw_lang_switcher(array $company, string $class = ''): string {
    $langs = yw_company_langs($company);
    if (count($langs) < 2) return '';
    $current = yw_lang();
    $back = (string)($_SERVER['REQUEST_URI'] ?? '/'.$company['slug']);
    $html = '<details class="yw-lang '.e($class).'"><summary aria-label="'.e(t('lang.choose')).'">'
        .'<svg class="cp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/></svg>'
        .'<span>'.e(YW_LANGS[$current]).'</span></summary>'
        .'<form method="post" action="/'.e($company['slug']).'/sprache">'.csrf_field().'<input type="hidden" name="back" value="'.e($back).'">';
    foreach ($langs as $code) {
        $html .= '<button type="submit" name="lang" value="'.e($code).'" lang="'.e($code).'"'.($code === $current ? ' aria-current="true"' : '').'>'.e(YW_LANGS[$code]).'</button>';
    }
    return $html.'</form></details>';
}

/** POST /<slug>/sprache – Sprache wählen (Cookie; angemeldet zusätzlich am Konto). */
function yw_lang_switch(string $slug): never {
    $q = db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');
    $q->execute([$slug]);
    $company = $q->fetch();
    if (!$company || !is_post()) redirect('/'.($company ? $slug : ''));
    verify_csrf();
    $code = (string)($_POST['lang'] ?? '');
    if (in_array($code, yw_company_langs($company), true)) {
        yw_lang_cookie_set($code);
        $account = company_portal_account($company);
        if ($account && !yw_is_preview($account)) {
            db()->prepare('UPDATE employee_accounts SET language=? WHERE id=? AND company_id=?')->execute([$code, (int)$account['id'], (int)$company['id']]);
        }
    }
    $back = (string)($_POST['back'] ?? '');
    // Nur zurück in denselben Shop (kein offener Redirect).
    if (!preg_match('#^/'.preg_quote($slug, '#').'(?:[/?\#][^\s\\\\]*)?$#D', $back) || str_starts_with($back, '//')) $back = '/'.$slug;
    redirect($back);
}
