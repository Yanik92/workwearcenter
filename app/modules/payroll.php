<?php
declare(strict_types=1);

/*
 * 10.11: Lohnabzug-Export – Eigenanteile der Mitarbeitenden je Monat als CSV für die Lohnbuchhaltung.
 * Maßgeblich ist der Freigabezeitpunkt (decided_at, bei Bestellungen ohne Freigabepflicht der
 * Bestellzeitpunkt). Nur freigegebene Bestellungen mit Eigenanteil zählen; zurückgezogene,
 * abgelehnte und stornierte Bestellungen nicht. Der Eigenanteil enthält einen vom Mitarbeiter
 * getragenen Versandanteil.
 */

/** Monat im Format JJJJ-MM; ungültige Eingaben ergeben den aktuellen Monat. */
function yw_payroll_month(mixed $raw): string {
    $raw=is_string($raw)?trim($raw):'';
    if(preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/D',$raw,$m) && (int)$m[1]>=2000 && (int)$m[1]<=2100)return $raw;
    return (new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('Y-m');
}

/** [Beginn, Ende) eines Monats als DATETIME-Text. */
function yw_payroll_range(string $month): array {
    $start=new DateTimeImmutable(yw_payroll_month($month).'-01 00:00:00',new DateTimeZone('Europe/Berlin'));
    return [$start->format('Y-m-d H:i:s'),$start->modify('+1 month')->format('Y-m-d H:i:s')];
}

function yw_payroll_month_label(string $month): string {
    return yw_month_label(yw_payroll_month($month)); // 10.12: Monatsnamen je Sprache (Verwaltung: Deutsch)
}

/** Hat die Firma überhaupt Eigenanteile (prozentual oder Versand trägt der Mitarbeiter)? */
function yw_payroll_has_share(array $company): bool {
    return (string)($company['financing_mode']??'company')==='percentage' || ((string)($company['shipping_payer']??'company')==='employee' && (int)($company['shipping_enabled']??0)===1);
}

/** Freigegebene Bestellungen mit Eigenanteil im Monat (eine Zeile je Bestellung). */
function yw_payroll_orders(int $companyId,string $month): array {
    [$start,$end]=yw_payroll_range($month);
    return yw_payroll_orders_between($companyId,$start,$end);
}

/**
 * Wie yw_payroll_orders für [Beginn, Ende); payroll_at = maßgeblicher Zeitpunkt COALESCE(decided_at,created_at).
 * Bedingung als zwei Bereiche (Freigabezeitpunkt bzw. ohne Freigabe der Bestellzeitpunkt) statt COALESCE(...) im WHERE,
 * damit der Index idx_yw_orders_payroll greift. $details=false: ohne Namen/Lieferdaten (Monatsübersicht zählt nur).
 */
function yw_payroll_orders_between(int $companyId,string $start,string $end,bool $details=true): array {
    $q=db()->prepare("SELECT o.id,o.employee_account_id,o.invite_id,o.employee_total_cents,COALESCE(o.decided_at,o.created_at) payroll_at".($details?",o.delivery_snapshot_json,
            ea.name,ea.employee_number,ea.cost_center,iv.employee_name invite_name
        FROM orders o
        LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id AND ea.company_id=o.company_id
        LEFT JOIN invites iv ON iv.id=o.invite_id AND iv.company_id=o.company_id":' FROM orders o')."
        WHERE o.company_id=? AND o.status='approved' AND o.employee_total_cents>0
          AND ((o.decided_at>=? AND o.decided_at<?) OR (o.decided_at IS NULL AND o.created_at>=? AND o.created_at<?))
        ORDER BY o.id");
    $q->execute([$companyId,$start,$end,$start,$end]);
    return $q->fetchAll();
}

/**
 * Fasst Bestellungen je Person zusammen (rein, ohne Datenbank – testbar).
 * Rückgabe sortiert nach Name: ['employee_number','name','cost_center','employee_cents','orders'=>[Auftragsnummern]].
 */
function yw_payroll_aggregate(array $orders): array {
    $rows=[];
    foreach($orders as $o){
        $snapshot=json_decode((string)($o['delivery_snapshot_json']??''),true);$snapshot=is_array($snapshot)?$snapshot:[];
        $accountId=(int)($o['employee_account_id']??0);
        $key=$accountId>0?'a'.$accountId:'i'.(int)($o['invite_id']??0);
        if(!isset($rows[$key])){
            $costCenter=trim((string)($o['cost_center']??''));
            if($costCenter==='')$costCenter=trim((string)($snapshot['cost_center']??''));
            $rows[$key]=[
                'employee_number'=>trim((string)($o['employee_number']??'')),
                'name'=>trim((string)($o['name']??''))!==''?trim((string)$o['name']):(trim((string)($o['invite_name']??''))?:trim((string)($snapshot['employee']??'Unbekannt'))),
                'cost_center'=>$costCenter,
                'employee_cents'=>0,
                'orders'=>[],
            ];
        }
        $rows[$key]['employee_cents']+=(int)$o['employee_total_cents'];
        $rows[$key]['orders'][]=yw_order_number((int)$o['id']);
    }
    $rows=array_values($rows);
    usort($rows,static fn(array $a,array $b): int => [mb_strtolower($a['name']),$a['employee_number']] <=> [mb_strtolower($b['name']),$b['employee_number']]);
    return $rows;
}

/** CSV-Zeilen (ohne Kopf) – Beträge mit Komma, Formel-Schutz über yw_csv_safe. */
function yw_payroll_csv_rows(string $month,array $rows): array {
    $out=[];
    foreach($rows as $r)$out[]=array_map('yw_csv_safe',[$month,$r['employee_number'],$r['name'],$r['cost_center'],number_format($r['employee_cents']/100,2,',',''),implode(', ',$r['orders'])]);
    return $out;
}

function yw_payroll_send_csv(array $company,string $month): never {
    $rows=yw_payroll_aggregate(yw_payroll_orders((int)$company['id'],$month));
    audit_log('payroll.exported','company',(int)$company['id'],['company_id'=>(int)$company['id'],'month'=>$month,'persons'=>count($rows),'sum_cents'=>array_sum(array_column($rows,'employee_cents'))]);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="lohnabzug-'.preg_replace('/[^a-z0-9-]/','',(string)$company['slug']).'-'.$month.'.csv"');
    header('Cache-Control: no-store');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Monat','Personalnummer','Name','Kostenstelle','Eigenanteil EUR','Auftragsnummern'],';','"','');
    foreach(yw_payroll_csv_rows($month,$rows) as $line)fputcsv($out,$line,';','"','');
    fclose($out);exit;
}

/** Übersicht der letzten Monate: Monat => ['sum','persons','orders']. */
function yw_payroll_overview(int $companyId,int $months=6): array {
    $result=[];$now=new DateTimeImmutable('first day of this month',new DateTimeZone('Europe/Berlin'));
    if($months<1)return $result;
    // Eine Abfrage für alle Monate, dann je Monat (JJJJ-MM des maßgeblichen Zeitpunkts) wie bisher zusammenfassen.
    $byMonth=[];
    foreach(yw_payroll_orders_between($companyId,yw_payroll_range($now->modify('-'.($months-1).' month')->format('Y-m'))[0],yw_payroll_range($now->format('Y-m'))[1],false) as $o)$byMonth[substr((string)$o['payroll_at'],0,7)][]=$o;
    for($i=0;$i<$months;$i++){
        $month=$now->modify('-'.$i.' month')->format('Y-m');
        $rows=yw_payroll_aggregate($byMonth[$month]??[]);
        $result[$month]=['sum'=>array_sum(array_column($rows,'employee_cents')),'persons'=>count($rows),'orders'=>array_sum(array_map(fn($r)=>count($r['orders']),$rows))];
    }
    return $result;
}

/** Download für Verantwortliche im Firmenshop: /<slug>/lohnabzug.csv?monat=JJJJ-MM */
function yw_payroll_manager_export(string $slug): never {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1');$q->execute([$slug]);$company=$q->fetch();if(!$company)redirect('/');
    $actor=company_portal_account($company);if(!$actor||!account_is_manager($actor)){http_response_code(403);exit('Kein Zugriff.');}
    yw_payroll_send_csv($company,yw_payroll_month($_GET['monat']??''));
}

/** Download in der Verwaltung: /admin/lohnabzug.csv?company=ID&monat=JJJJ-MM */
function yw_payroll_admin_export(): never {
    require_admin();
    $q=db()->prepare('SELECT * FROM companies WHERE id=?');$q->execute([(int)($_GET['company']??0)]);$company=$q->fetch();
    if(!$company){http_response_code(404);exit('Firma nicht gefunden.');}
    yw_payroll_send_csv($company,yw_payroll_month($_GET['monat']??''));
}

function yw_payroll_table(array $overview,callable $link): string {
    $html='<div style="overflow:auto"><table class="table"><thead><tr><th>'.e(t('payroll.month')).'</th><th>'.e(t('payroll.persons')).'</th><th>'.e(t('payroll.orders')).'</th><th>'.e(t('payroll.share')).'</th><th></th></tr></thead><tbody>';
    foreach($overview as $month=>$o)$html.='<tr><td>'.e(yw_payroll_month_label($month)).'</td><td>'.(int)$o['persons'].'</td><td>'.(int)$o['orders'].'</td><td>'.money((int)$o['sum']).'</td><td><a href="'.e($link($month)).'">CSV</a></td></tr>';
    return $html.'</tbody></table></div>';
}

/** Karte im Firmenshop (Bereich Mitarbeiter/Auswertung, nur Verantwortliche). */
function yw_payroll_manager_card(array $company): void {
    if(!yw_payroll_has_share($company))return;
    $slug=(string)$company['slug'];
    echo '<section class="cp-card cp-report"><div class="cp-report-head"><div><h2>'.e(t('payroll.title')).'</h2><p>'.e(t('payroll.intro')).'</p></div>';
    echo '<form method="get" action="/'.e($slug).'/lohnabzug.csv" class="cp-report-actions"><label>'.e(t('payroll.month')).' <input type="month" name="monat" value="'.e(yw_payroll_month('')).'" required></label><button class="cp-btn" type="submit">'.e(t('payroll.download')).'</button></form></div>';
    echo yw_payroll_table(yw_payroll_overview((int)$company['id']),fn(string $m): string => '/'.$slug.'/lohnabzug.csv?monat='.$m);
    echo '<p class="muted">'.e(t('payroll.note')).'</p></section>';
}

/** Karte in der Kundenakte der Verwaltung. */
function yw_payroll_admin_card(array $company): void {
    $id=(int)$company['id'];
    echo '<section class="card"><div class="title-row"><div><h2>Lohnabzug Eigenanteil</h2><p class="muted">'.(yw_payroll_has_share($company)?'Eigenanteile je Mitarbeiter aus den im Monat freigegebenen Bestellungen.':'Diese Firma übernimmt derzeit 100 %. Frühere Eigenanteile bleiben abrufbar.').'</p></div>';
    echo '<form method="get" action="/admin/lohnabzug.csv" class="inline-form"><input type="hidden" name="company" value="'.$id.'"><label>Monat <input type="month" name="monat" value="'.e(yw_payroll_month('')).'" required></label> <button class="btn small" type="submit">CSV</button></form></div>';
    echo yw_payroll_table(yw_payroll_overview($id),fn(string $m): string => '/admin/lohnabzug.csv?company='.$id.'&monat='.$m);
    echo '</section>';
}
