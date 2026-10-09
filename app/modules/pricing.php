<?php
declare(strict_types=1);

/*
 * 10.11: Rechenkern für Bestellbeträge – Rundung wie Lexware Office bei Rechnungen mit Nettopreisen.
 *
 * Lexware Office (Public API, Invoices, taxConditions.taxType = "net") rechnet so:
 *   - Positionsbetrag (lineItemAmount) = Netto-Einzelpreis × Menge, netto
 *   - Umsatzsteuer je Steuersatz auf die Summe der Nettobeträge dieses Satzes
 *     (taxAmounts[].taxAmount: „The total tax amount for this tax rate“), kaufmännisch auf Cent
 *   - Brutto gesamt = Netto gesamt + Summe der Steuerbeträge
 * Beispiel aus der Lexware-Dokumentation: 13,40 € netto zu 19 % → 2,55 € Steuer (2,546 gerundet),
 * 8,32 € zu 7 % → 0,58 € (0,5824 gerundet).
 *
 * Für Budget, Eigenanteil und Firmenanteil braucht jede Position trotzdem einen Bruttobetrag.
 * Dazu wird die Steuer eines Satzes nach der Methode der größten Reste auf die Positionen
 * verteilt (Summe der Positionen = Steuer des Satzes, nie ein Cent mehr oder weniger).
 * Versand ist eine eigene Position: Seine Steuer ist die Differenz zwischen der Steuer des
 * Satzes mit und ohne Versand. So bleibt der Warenwert unabhängig vom Versand, und die
 * Bestellsumme entspricht exakt dem, was Lexware für dieselben Positionen ausweist.
 * Alle Rechnungen erfolgen in ganzen Cent (Integer), damit keine Gleitkommafehler entstehen.
 */

/** Steuersatz/Prozentsatz in Hundertstelprozent (19 % → 1900), begrenzt auf 0 … 100 %. */
function yw_rate_bp(float $rate): int {
    return max(0,min(10000,(int)round($rate*100)));
}

/** Kaufmännisch gerundeter Anteil in Cent (Beträge sind nie negativ). */
function yw_percent_of(int $cents,float $percent): int {
    return intdiv(max(0,$cents)*yw_rate_bp($percent)+5000,10000);
}

/** Umsatzsteuer auf eine Nettosumme eines Steuersatzes (wie Lexware taxAmounts). */
function yw_tax_for_net(int $netCents,int $rateBp): int {
    return intdiv(max(0,$netCents)*$rateBp+5000,10000);
}

/**
 * Berechnet Warenpositionen.
 * $lines: Liste von ['net_unit'=>Cent, 'qty'=>Stück, 'vat'=>Prozent]
 * Rückgabe: lines (je Position net, tax, gross, employee, company, vat), Summen und net_by_rate (bp => Netto).
 */
function yw_calc_items(array $lines,float $employeePercent=0.0): array {
    $out=[];$groups=[];
    foreach(array_values($lines) as $i=>$line){
        $qty=max(0,(int)($line['qty']??0));
        $net=max(0,(int)($line['net_unit']??0))*$qty;
        $bp=yw_rate_bp((float)($line['vat']??19.0));
        $out[$i]=['net'=>$net,'tax'=>0,'gross'=>0,'employee'=>0,'company'=>0,'vat'=>$bp/100,'qty'=>$qty,'net_unit'=>max(0,(int)($line['net_unit']??0))];
        $groups[$bp][]=$i;
    }
    $netByRate=[];$taxByRate=[];
    ksort($groups);
    foreach($groups as $bp=>$indexes){
        $sum=0;$floors=0;$remainders=[];
        foreach($indexes as $i){
            $exact=$out[$i]['net']*$bp;
            $out[$i]['tax']=intdiv($exact,10000);
            $floors+=$out[$i]['tax'];
            $remainders[$i]=$exact%10000;
            $sum+=$out[$i]['net'];
        }
        $tax=yw_tax_for_net($sum,$bp);
        $extra=$tax-$floors;
        // größte Reste zuerst, bei Gleichstand die frühere Position
        uksort($remainders,static fn(int $a,int $b): int => [$remainders[$b],$a] <=> [$remainders[$a],$b]);
        foreach(array_keys($remainders) as $i){if($extra<=0)break;$out[$i]['tax']++;$extra--;}
        $netByRate[$bp]=$sum;$taxByRate[$bp]=$tax;
    }
    $totals=['net'=>0,'tax'=>0,'gross'=>0,'employee'=>0,'company'=>0];
    foreach($out as &$line){
        $line['gross']=$line['net']+$line['tax'];
        $line['employee']=yw_percent_of($line['gross'],$employeePercent);
        $line['company']=$line['gross']-$line['employee'];
        foreach($totals as $k=>$_)$totals[$k]+=$line[$k];
    }
    unset($line);
    return $totals+['lines'=>array_values($out),'net_by_rate'=>$netByRate,'tax_by_rate'=>$taxByRate];
}

/** Steuer der Versandposition: Steuer des Satzes mit Versand minus Steuer ohne Versand. */
function yw_shipping_tax_cents(array $itemsNetByRate,int $shippingNet,float $vat): int {
    $bp=yw_rate_bp($vat);
    $itemsNet=(int)($itemsNetByRate[$bp]??0);
    return yw_tax_for_net($itemsNet+max(0,$shippingNet),$bp)-yw_tax_for_net($itemsNet,$bp);
}

/**
 * Erwartete Rechnungssumme für beliebige Positionen (wie Lexware): ['net','tax','gross','by_rate'=>[bp=>['net','tax']]].
 * $lines: Liste von ['net_unit'=>Cent,'qty'=>Stück,'vat'=>Prozent].
 */
function yw_invoice_totals(array $lines): array {
    $byRate=[];
    foreach($lines as $line){
        $bp=yw_rate_bp((float)($line['vat']??19.0));
        $byRate[$bp]=($byRate[$bp]??0)+max(0,(int)($line['net_unit']??0))*max(0,(int)($line['qty']??0));
    }
    ksort($byRate);
    $result=['net'=>0,'tax'=>0,'gross'=>0,'by_rate'=>[]];
    foreach($byRate as $bp=>$net){$tax=yw_tax_for_net($net,$bp);$result['by_rate'][$bp]=['net'=>$net,'tax'=>$tax];$result['net']+=$net;$result['tax']+=$tax;}
    $result['gross']=$result['net']+$result['tax'];
    return $result;
}

/**
 * Beträge einer gespeicherten Bestellposition.
 * Ab 10.11 stehen sie als Positionswerte in order_items (line_*); ältere Bestellungen
 * bleiben unverändert und werden wie bisher aus Stückwert × Menge gelesen.
 */
function yw_order_item_amounts(array $item): array {
    $qty=(int)($item['quantity']??0);
    if(isset($item['line_gross_cents']) && $item['line_gross_cents']!==null){
        return ['net'=>(int)$item['line_net_cents'],'gross'=>(int)$item['line_gross_cents'],'employee'=>(int)$item['line_employee_cents'],'company'=>(int)$item['line_company_cents']];
    }
    $unitNet=(int)($item['unit_net_cents']??0);if($unitNet<=0)$unitNet=(int)($item['unit_price_cents']??0);
    $unitGross=(int)($item['unit_gross_cents']??0);if($unitGross<=0)$unitGross=(int)($item['unit_price_cents']??0);
    return ['net'=>$unitNet*$qty,'gross'=>$unitGross*$qty,'employee'=>(int)($item['employee_share_cents']??0)*$qty,'company'=>(int)($item['company_share_cents']??0)*$qty];
}
