<?php
declare(strict_types=1);

/*
 * 10.11: Bestellfenster (Sammelbestellung), Jahreswechsel-Mail „Dein neues Budget ist da“
 * und die Firmeneinstellungen dazu (Kundenakte → Regeln & Budget).
 *
 * Sammelbestellung: Freigegebene Bestellungen einer Firma werden nicht sofort, sondern
 * gesammelt zum nächsten Stichtag an die Auftragszentrale übergeben. Technisch erhält der
 * Übergabedatensatz (yw_handover) available_at = Stichtag; Push-Warteschlange und Pull-API
 * berücksichtigen available_at, bis dahin bleibt der Auftrag zurückgehalten.
 */

/** Ist der Datenbankstand 10.11 eingespielt? (pro Anfrage einmal geprüft) */
function yw_orders_schema_ready(): bool {
    static $ready=null;
    if($ready!==null)return $ready;
    // Vollständiger Stand (yw_schema_ready: 10.12 und alle Spalten) schließt 10.11 ein – dann ohne eigene Abfrage.
    try{$ready=(function_exists('yw_schema_ready')&&yw_schema_ready()) || (bool)db()->query("SELECT 1 FROM yw_schema_versions WHERE version='10.11.0'")->fetchColumn();}
    catch(Throwable $e){$ready=false;}
    return $ready;
}

/** Erlaubte Sammelmodi. */
function yw_batch_modes(): array {
    return ['off'=>'Aus – sofort übergeben','monthly'=>'Monatlich zum Stichtag','biweekly'=>'Alle 14 Tage'];
}

/**
 * Nächster Sammeltermin (00:00 Uhr Ortszeit) strikt nach $now, oder null ohne Sammelbestellung.
 * monthly: Tag $day (1–28) jedes Monats · biweekly: alle 14 Tage ab $anchor (Y-m-d).
 */
function yw_batch_next_date(string $mode,int $day,?string $anchor,?DateTimeImmutable $now=null): ?DateTimeImmutable {
    $tz=new DateTimeZone('Europe/Berlin');
    $now=$now??new DateTimeImmutable('now',$tz);
    if($mode==='monthly'){
        $day=max(1,min(28,$day));
        $candidate=$now->setDate((int)$now->format('Y'),(int)$now->format('n'),$day)->setTime(0,0);
        if($candidate<=$now){
            $nextMonth=$now->modify('first day of next month');
            $candidate=$nextMonth->setDate((int)$nextMonth->format('Y'),(int)$nextMonth->format('n'),$day)->setTime(0,0);
        }
        return $candidate;
    }
    if($mode==='biweekly'){
        $start=($anchor!==null && preg_match('/^\d{4}-\d{2}-\d{2}$/D',$anchor))?new DateTimeImmutable($anchor.' 00:00:00',$tz):$now->setTime(0,0);
        if($start>$now)return $start;
        // Kalendertage zählen (Sommerzeit-sicher), dann auf das nächste Vielfache von 14 runden.
        $days=(int)$start->diff($now->setTime(0,0))->days;
        $steps=intdiv($days,14)+1;
        return $start->modify('+'.($steps*14).' days');
    }
    return null;
}

/** Nächster Sammeltermin einer Firma (Firmenzeile aus companies). */
function yw_company_batch_date(array $company,?DateTimeImmutable $now=null): ?DateTimeImmutable {
    if(!isset($company['batch_mode']))return null;
    return yw_batch_next_date((string)$company['batch_mode'],(int)($company['batch_day']??1),$company['batch_anchor']??null,$now);
}

/** Zeitpunkt, ab dem der Übergabedatensatz einer Bestellung gesendet bzw. abgerufen werden darf. */
function yw_batch_available_at(int $orderId): string {
    $fallback=date('Y-m-d H:i:s');
    if(!yw_orders_schema_ready())return $fallback;
    try{
        $q=db()->prepare('SELECT c.batch_mode,c.batch_day,c.batch_anchor FROM orders o JOIN companies c ON c.id=o.company_id WHERE o.id=? LIMIT 1');
        $q->execute([$orderId]);$company=$q->fetch();
        $date=$company?yw_company_batch_date($company):null;
        return $date?$date->format('Y-m-d H:i:s'):$fallback;
    }catch(Throwable $e){error_log('WorkwearCenter batch date #'.$orderId.': '.$e->getMessage());return $fallback;}
}

/** Hinweis für Mitarbeitende im Shop (leer ohne Sammelbestellung). */
function yw_batch_shop_notice(array $company): string {
    $date=yw_company_batch_date($company);
    if(!$date)return '';
    return '<div class="cp-manager-alert yw-batch-notice"><strong>'.e(t('batch.next',['date'=>yw_date($date)])).'</strong><br>'.e(t('batch.text')).'</div>';
}

/** Zurückgehaltene Übergaben einer Firma nach geänderten Sammeleinstellungen neu terminieren. */
function yw_batch_reschedule_company(int $companyId): int {
    $q=db()->prepare('SELECT batch_mode,batch_day,batch_anchor FROM companies WHERE id=?');$q->execute([$companyId]);$company=$q->fetch();
    if(!$company)return 0;
    $date=yw_company_batch_date($company);
    $at=$date?$date->format('Y-m-d H:i:s'):date('Y-m-d H:i:s');
    $u=db()->prepare("UPDATE yw_handover h JOIN orders o ON o.id=h.order_id SET h.available_at=? WHERE o.company_id=? AND h.event='order.approved' AND h.status='pending' AND h.attempts=0 AND h.available_at>NOW()");
    $u->execute([$at,$companyId]);
    return $u->rowCount();
}

/* ---------- Jahreswechsel-Mail ---------- */

/**
 * Soll für dieses Budgetjahr (Start $periodStart) jetzt die Jahreswechsel-Mail gehen?
 * Nur in den ersten $graceDays Tagen des neuen Budgetjahres – so löst das Einschalten
 * mitten im Jahr keine „neues Budget“-Mail aus – und nur einmal je Budgetjahr.
 */
function yw_budget_mail_due(string $periodStart,?string $alreadySentFor,DateTimeImmutable $now,int $graceDays=14): bool {
    $start=new DateTimeImmutable($periodStart,new DateTimeZone('Europe/Berlin'));
    if($alreadySentFor!==null && $alreadySentFor!=='' && substr($alreadySentFor,0,10)===$start->format('Y-m-d'))return false;
    return $now>=$start && $now<$start->modify('+'.$graceDays.' days');
}

/** Verschickt die Mail „Dein neues Budget ist da“ (Cron und tägliche Rückfallebene). Rückgabe: Anzahl Mails. */
function yw_send_budget_year_mails(int $limit=500): int {
    if(!yw_orders_schema_ready())return 0;
    $sent=0;
    try{
        $now=new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin'));
        foreach(db()->query("SELECT * FROM companies WHERE active=1 AND budget_year_mail=1 AND budget_enabled=1")->fetchAll() as $company){
            [$start,$end]=yw_period((int)$company['budget_reset_month'],$now);
            if(!yw_budget_mail_due($start,$company['budget_year_mail_period'],$now))continue;
            $period=substr($start,0,10);
            $q=db()->prepare("SELECT *,? shop_languages FROM employee_accounts WHERE company_id=? AND active=1 AND registration_status='approved' AND anonymized_at IS NULL AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) AND (budget_mail_period IS NULL OR budget_mail_period<>?) ORDER BY id");
            $q->execute([$company['shop_languages']??null,(int)$company['id'],$period]);
            $complete=true;
            foreach($q->fetchAll() as $account){
                if($sent>=$limit){$complete=false;break;}
                // Merker je Person atomar setzen: parallel laufende Cron-/Webprozesse verschicken nie doppelt.
                $claim=db()->prepare('UPDATE employee_accounts SET budget_mail_period=? WHERE id=? AND (budget_mail_period IS NULL OR budget_mail_period<>?)');
                $claim->execute([$period,(int)$account['id'],$period]);
                if(!$claim->rowCount())continue;
                $limitCents=yw_budget_limit($company,$account);
                if(!$limitCents)continue; // Budget 0 € – keine „neues Budget“-Mail
                // 10.12: in der Sprache der Person (Beträge und Datum im Format dieser Sprache)
                $shopUrl=workwearcenter_url((string)$company['slug'].'/produkte');
                yw_mail_to_person($account,fn(array $p)=>[t('mail.budget.subject'),
                    t('mail.hello',['name'=>$p['name']])."\n\n".t('mail.budget.body',['company'=>$company['legal_name']])."\n\n".t('mail.budget.amount').': '.money((int)$limitCents)."\n".t('mail.budget.year').': '.yw_date($start).' – '.yw_date((new DateTimeImmutable($end))->modify('-1 day'))."\n\n".t('mail.budget.to_shop').': '.$shopUrl]);
                $sent++;
            }
            if($complete){
                // Firmenmerker: dieses Budgetjahr ist erledigt, die Firma wird bis zum nächsten nicht mehr geprüft.
                db()->prepare('UPDATE companies SET budget_year_mail_period=? WHERE id=?')->execute([$period,(int)$company['id']]);
                audit_log('company.budget_year_mail','company',(int)$company['id'],['company_id'=>(int)$company['id'],'period'=>$period]);
            }else break;
        }
    }catch(Throwable $e){error_log('WorkwearCenter budget year mail: '.$e->getMessage());}
    return $sent;
}

/** Aufräumen abgelaufener Einmal-Links (Cron). */
function yw_orders_cleanup(): void {
    if(!yw_orders_schema_ready())return;
    try{
        db()->exec("DELETE FROM yw_login_links WHERE created_at<DATE_SUB(NOW(),INTERVAL 2 DAY)");
        db()->exec("DELETE FROM yw_decision_tokens WHERE created_at<DATE_SUB(NOW(),INTERVAL 30 DAY)");
    }catch(Throwable $e){error_log('WorkwearCenter orders cleanup: '.$e->getMessage());}
}

/* ---------- Firmeneinstellungen (Kundenakte → Regeln & Budget) ---------- */

function yw_customer_section_order_options(array $company): void {
    if(!yw_orders_schema_ready()){echo '<div class="flash info">Für Umtausch, Jahreswechsel-Mail und Sammelbestellung bitte zuerst den Update-Assistenten ausführen.</div>';return;}
    $id=(int)$company['id'];
    $mode=(string)($company['batch_mode']??'off');
    $next=yw_company_batch_date($company);
    echo '<details class="card admin-toolbox" open><summary>Umtausch, Jahreswechsel, Sammelbestellung & Sprachen</summary><div class="admin-toolbox-body">';
    echo '<form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="company_order_options"><input type="hidden" name="company_id" value="'.$id.'">';
    echo '<label>Frist für Größentausch und Rückgabe (Tage ab Versand bzw. Freigabe)<input type="number" name="exchange_window_days" min="0" max="365" value="'.(int)($company['exchange_window_days']??30).'" required></label><p class="muted">0 = Mitarbeitende können im Shop keinen Tausch und keine Rückgabe beantragen.</p>';
    echo '<label class="check"><input type="checkbox" name="budget_year_mail" value="1" '.((int)($company['budget_year_mail']??1)?'checked':'').'><span>Mail „Dein neues Budget ist da“ zu Beginn jedes Budgetjahres (nur bei aktivem Geldbudget)</span></label>';
    echo '<label>Sammelbestellung<select name="batch_mode">';
    foreach(yw_batch_modes() as $key=>$label)echo '<option value="'.e($key).'" '.($mode===$key?'selected':'').'>'.e($label).'</option>';
    echo '</select></label>';
    echo '<label>Stichtag im Monat (bei „Monatlich“)<input type="number" name="batch_day" min="1" max="28" value="'.(int)($company['batch_day']??1).'"></label>';
    echo '<label>Erster Termin (bei „Alle 14 Tage“)<input type="date" name="batch_anchor" value="'.e((string)($company['batch_anchor']??'')).'"></label>';
    // 10.12: Sprachen des Firmenshops (Deutsch immer); Website und Verwaltung bleiben deutsch.
    $offered=yw_company_langs($company);
    echo '<fieldset class="yw-lang-options" style="border:1px solid #e4e8ef;border-radius:10px;padding:10px 14px"><legend style="font-weight:700;padding:0 4px">Sprachen im Firmenshop</legend><p class="muted">Mitarbeitende wählen ihre Sprache im Shop; Mails gehen in der gewählten Sprache. Produktnamen und -beschreibungen bleiben wie gepflegt.</p><div style="display:flex;flex-wrap:wrap;gap:6px 20px">';
    foreach(YW_LANGS as $code=>$label)echo '<label class="check"><input type="checkbox" name="shop_languages[]" value="'.e($code).'" '.(in_array($code,$offered,true)?'checked':'').($code==='de'?' disabled':'').'><span lang="'.e($code).'">'.e($label).($code==='de'?' (immer)':'').'</span></label>';
    echo '</div></fieldset>';
    echo '<p class="muted">'.($next?'Nächster Sammeltermin: <strong>'.e($next->format('d.m.Y')).'</strong>. Freigegebene Bestellungen werden bis dahin zurückgehalten und dann gemeinsam übergeben.':'Freigegebene Bestellungen werden sofort an die Auftragszentrale übergeben.').'</p>';
    echo '<button class="btn primary">Einstellungen speichern</button></form></div></details>';
}

function yw_admin_order_options_action(): never {
    $companyId=(int)($_POST['company_id']??0);
    try{
        $days=(string)($_POST['exchange_window_days']??'30');
        if(!preg_match('/^\d{1,3}$/D',$days)||(int)$days>365)throw new RuntimeException('Frist: 0 bis 365 Tage.');
        $mode=(string)($_POST['batch_mode']??'off');
        if(!array_key_exists($mode,yw_batch_modes()))throw new RuntimeException('Sammelmodus ungültig.');
        $day=(int)($_POST['batch_day']??1);
        if($day<1||$day>28)throw new RuntimeException('Stichtag: 1 bis 28.');
        $anchor=trim((string)($_POST['batch_anchor']??''));
        if($anchor!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$anchor) || !checkdate((int)substr($anchor,5,2),(int)substr($anchor,8,2),(int)substr($anchor,0,4))))throw new RuntimeException('Erster Termin ist kein gültiges Datum.');
        if($mode==='biweekly' && $anchor==='')$anchor=date('Y-m-d',strtotime('+1 day'));
        $languages=yw_company_langs_value(is_array($_POST['shop_languages']??null)?$_POST['shop_languages']:[]);
        $q=db()->prepare('UPDATE companies SET exchange_window_days=?,budget_year_mail=?,batch_mode=?,batch_day=?,batch_anchor=?,shop_languages=? WHERE id=?');
        $q->execute([(int)$days,!empty($_POST['budget_year_mail'])?1:0,$mode,$day,$anchor!==''?$anchor:null,$languages,$companyId]);
        $moved=yw_batch_reschedule_company($companyId);
        audit_log('company.order_options','company',$companyId,['company_id'=>$companyId,'batch_mode'=>$mode,'exchange_window_days'=>(int)$days,'rescheduled'=>$moved,'shop_languages'=>$languages??'alle']);
        flash('success','Einstellungen gespeichert.'.($moved?' '.$moved.' zurückgehaltene Übergabe(n) neu terminiert.':''));
    }catch(Throwable $e){
        if(!$e instanceof RuntimeException)error_log('Order options: '.$e->getMessage());
        flash('error',$e instanceof RuntimeException?$e->getMessage():'Einstellungen konnten nicht gespeichert werden.');
    }
    redirect(yw_customer_url($companyId,'regeln'));
}

/** Einstieg für alle Admin-Aktionen dieser Erweiterung (aus handle_admin_action). */
function yw_orders_admin_action(string $action): void {
    if($action==='company_order_options')yw_admin_order_options_action();
    if(str_starts_with($action,'exchange_'))yw_admin_exchange_action($action);
}
