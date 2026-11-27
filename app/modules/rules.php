<?php
declare(strict_types=1);

function yw_money_input(string $value): int {
    $value=str_replace(',','.',trim($value));
    if(!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$value)) throw new InvalidArgumentException('Betrag bitte als nicht negative Zahl mit höchstens zwei Nachkommastellen eingeben.');
    $parts=explode('.',$value);
    return (int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
}

function yw_decimal_input(string $value,float $min,float $max,string $label): float {
    $value=str_replace(',','.',trim($value));
    if(!preg_match('/^\d{1,7}(?:\.\d{1,4})?$/D',$value))throw new InvalidArgumentException($label.' ist keine gültige Zahl.');
    $number=(float)$value;
    if(!is_finite($number) || $number<$min || $number>$max)throw new InvalidArgumentException($label.' liegt außerhalb des zulässigen Bereichs.');
    return $number;
}

function yw_period(int $resetMonth, ?DateTimeImmutable $now=null): array {
    if($resetMonth<1 || $resetMonth>12) throw new InvalidArgumentException('Budgetmonat ungültig.');
    $now=$now??new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin'));
    $start=$now->setDate((int)$now->format('Y'),$resetMonth,1)->setTime(0,0);
    if($start>$now) $start=$start->modify('-1 year');
    return [$start->format('Y-m-d H:i:s'),$start->modify('+1 year')->format('Y-m-d H:i:s')];
}

function yw_budget_limit(array $company,array $account): ?int {
    if(!(int)($company['budget_enabled']??0)) return null;
    return max(0,(int)($account['budget_override_cents']??$company['annual_budget_company_cents']??0));
}

function yw_check_limits(?int $budget,int $used,int $requested,?int $quantityLimit=null,int $quantityUsed=0,int $quantityRequested=0): void {
    if($budget!==null && $used+$requested>$budget) throw new DomainException(t('err.over_budget'));
    if($quantityLimit!==null && $quantityUsed+$quantityRequested>$quantityLimit) throw new DomainException(t('err.over_limit'));
}

function yw_registration_policy(string $mode): array {
    return match($mode) {
        'open'=>[false,false],
        'approval'=>[false,true],
        'pin'=>[true,false],
        'pin_approval'=>[true,true],
        default=>throw new DomainException('Registrierungsmodus unbekannt. Bitte den Betreiber kontaktieren.'),
    };
}

function yw_csv_safe(mixed $value): string {
    $text=(string)$value;
    return preg_match('/^[\s]*[=+@-]/u',$text) ? "'".$text : $text;
}

/* ---------- 10.27: Individuelles Budget je Person ---------- */

/** Eingabe „eigenes Budget“: leer = Firmenstandard (NULL), sonst Cent-Betrag. */
function yw_budget_override_input(mixed $raw): ?int {
    $raw=is_string($raw)?trim($raw):'';
    return $raw===''?null:yw_money_input($raw);
}

/** Eigenes Budget speichern und protokollieren. Rückgabe: true, wenn sich etwas geändert hat. */
function yw_budget_override_save(int $companyId,int $accountId,?int $cents,string $via): bool {
    $q=db()->prepare('SELECT budget_override_cents FROM employee_accounts WHERE id=? AND company_id=?');$q->execute([$accountId,$companyId]);
    $before=$q->fetchColumn();if($before===false)throw new DomainException('Mitarbeiter nicht gefunden.');
    $before=$before===null?null:(int)$before;
    if($before===$cents)return false;
    db()->prepare('UPDATE employee_accounts SET budget_override_cents=? WHERE id=? AND company_id=?')->execute([$cents,$accountId,$companyId]);
    audit_log('employee.budget_changed','employee',$accountId,['company_id'=>$companyId,'before_cents'=>$before,'after_cents'=>$cents,'via'=>$via]);
    return true;
}

/**
 * Eingabefeld „Eigenes Budget“ für eine Person. $t: Texte [label, default (mit {amount}), off, used (mit {used} {limit})].
 * $disabled sperrt das Feld (eigenes Konto oder andere Verantwortliche im Firmenshop).
 */
function yw_budget_override_field(array $company,array $account,array $t,bool $disabled=false,string $lockHint=''): string {
    $value=$account['budget_override_cents']===null||$account['budget_override_cents']===''?'':number_format((int)$account['budget_override_cents']/100,2,'.','');
    $enabled=(int)($company['budget_enabled']??0)===1;
    $default=money((int)($company['annual_budget_company_cents']??0));
    $hint=$enabled?str_replace('{amount}',$default,$t[1]):$t[2];
    if($enabled&&!empty($account['id'])&&function_exists('yw_budget_used')){
        try{$used=yw_budget_used($company,$account);$hint.=' '.str_replace(['{used}','{limit}'],[money($used),money((int)yw_budget_limit($company,$account))],$t[3]);}catch(Throwable $e){}
    }
    if($lockHint!=='')$hint.=' '.$lockHint;
    return '<label class="yw-budget-field">'.e($t[0]).'<input name="budget_override" type="number" min="0" max="9999999" step="0.01" inputmode="decimal" placeholder="'.e($enabled?$default:'').'" value="'.e($value).'"'.($disabled?' disabled':'').'><small class="muted">'.e($hint).'</small></label>';
}
