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
