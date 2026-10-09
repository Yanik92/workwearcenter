<?php
declare(strict_types=1);

/*
 * 10.11: Größentausch und Rückgabe („Umtausch & Retouren“).
 *
 * Ablauf: Mitarbeitende beantragen je Position einer freigegebenen Bestellung einen Größentausch
 * oder eine Rückgabe (mit Grund, innerhalb der Firmenfrist). Mit interner Freigabe entscheiden die
 * Verantwortlichen (gleiche Vier-Augen-Regel wie bei Bestellungen), sonst geht der Antrag direkt an
 * die Verwaltung. Status: requested → approved → received → done, jederzeit vorher rejected.
 *
 * Budget: Rückgabe schreibt den Firmenanteil der Position nach Abschluss (done) gut. Tausch ist
 * budgetneutral; eine Preisdifferenz (z. B. Übergröße) wird verrechnet – Mehrkosten reservieren
 * Budget ab Antrag (wie eine offene Bestellung), Minderkosten werden nach Abschluss gutgeschrieben.
 * Verbucht wird im Budgetjahr der ursprünglichen Bestellung.
 *
 * Übergabe: Freigegebene Tauschanträge erzeugen einen Datensatz in yw_handover
 * (event 'exchange.approved:<id>', Payload-Schema workwearcenter.exchange.v1). Im Format
 * 'auftragszentrale' wird er nicht automatisch gesendet, sondern als „manuell übertragen“ geführt,
 * bis die Auftragszentrale Tauschaufträge annehmen kann (SCHNITTSTELLE-AUFTRAGSVERWALTUNG.md).
 */

const YW_EXCHANGE_SCHEMA = 'workwearcenter.exchange.v1';

function yw_exchange_statuses(): array {
    return ['requested'=>'Beantragt','approved'=>'Freigegeben','received'=>'Ware erhalten','done'=>'Erledigt','rejected'=>'Abgelehnt'];
}

function yw_exchange_type_label(string $type): string {
    return $type==='return'?'Rückgabe':'Größentausch';
}

function yw_exchange_badge(string $status): string {
    $class=['requested'=>'pending','approved'=>'approved','received'=>'approved','done'=>'approved','rejected'=>'rejected'][$status]??'';
    return '<span class="badge '.$class.' yw-x-badge yw-x-'.e($status).'">'.e(yw_exchange_statuses()[$status]??$status).'</span>';
}

/** Zulässige Statuswechsel. */
function yw_exchange_transition_allowed(string $from,string $to): bool {
    return in_array($to,[
        'requested'=>['approved','rejected'],
        'approved'=>['received','done','rejected'],
        'received'=>['done','rejected'],
    ][$from]??[],true);
}

/** Budgetwirkung eines Antrags in Cent (positiv = belastet, negativ = Gutschrift). */
function yw_exchange_budget_effect(string $type,string $status,int $delta): int {
    if($status==='done')return $delta;
    if($type==='exchange' && $delta>0 && in_array($status,['requested','approved','received'],true))return $delta;
    return 0;
}

/** Fristbeginn: Versand bzw. Abholung, sonst Freigabe der Bestellung. */
function yw_exchange_window_base(array $order): string {
    if(in_array((string)($order['fulfillment_status']??''),['shipped','picked_up','completed'],true) && !empty($order['production_updated_at']))return (string)$order['production_updated_at'];
    return (string)($order['decided_at']?:$order['created_at']);
}

/** Letzter Tag der Frist (23:59:59) oder null, wenn die Firma Umtausch abgeschaltet hat. */
function yw_exchange_deadline(string $base,int $days): ?DateTimeImmutable {
    if($days<=0)return null;
    return (new DateTimeImmutable($base,new DateTimeZone('Europe/Berlin')))->modify('+'.$days.' days')->setTime(23,59,59);
}

function yw_exchange_window_open(array $company,array $order,?DateTimeImmutable $now=null): bool {
    if((string)$order['status']!=='approved')return false;
    $deadline=yw_exchange_deadline(yw_exchange_window_base($order),(int)($company['exchange_window_days']??30));
    return $deadline!==null && ($now??new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))<=$deadline;
}

/** Firmenanteil je Stück für eine Größe (heute einheitlich je Artikel; Größenpreise optional per Hook). */
function yw_exchange_unit_company_cents(array $product,array $company,string $size): int {
    if(function_exists('yw_product_size_price_breakdown'))return (int)(yw_product_size_price_breakdown($product,$company,$size)['employer']??0);
    return (int)product_price_breakdown($product,$company)['employer'];
}

/* ---------- Budget-Verrechnung (wird von yw_budget_used / yw_quantity_used genutzt) ---------- */

function yw_exchange_budget_adjustment(array $company,array $account,string $start,string $end): int {
    if(!yw_orders_schema_ready())return 0;
    try{
        $q=db()->prepare("SELECT COALESCE(SUM(r.budget_delta_cents),0) FROM yw_exchange_requests r JOIN orders o ON o.id=r.order_id
            WHERE r.company_id=? AND o.company_id=r.company_id AND (o.employee_account_id=? OR (o.employee_account_id IS NULL AND o.invite_id=?)) AND o.status='approved' AND o.created_at>=? AND o.created_at<?
            AND (r.status='done' OR (r.type='exchange' AND r.budget_delta_cents>0 AND r.status IN ('requested','approved','received')))");
        $q->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0),$start,$end]);
        return (int)$q->fetchColumn();
    }catch(Throwable $e){error_log('WorkwearCenter exchange budget: '.$e->getMessage());return 0;}
}

function yw_exchange_returned_qty(array $company,array $account,int $productId,string $start,string $end): int {
    if(!yw_orders_schema_ready())return 0;
    try{
        $q=db()->prepare("SELECT COALESCE(SUM(r.quantity),0) FROM yw_exchange_requests r JOIN orders o ON o.id=r.order_id JOIN order_items i ON i.id=r.order_item_id
            WHERE r.company_id=? AND r.type='return' AND r.status='done' AND (o.employee_account_id=? OR (o.employee_account_id IS NULL AND o.invite_id=?)) AND o.status='approved' AND o.created_at>=? AND o.created_at<? AND i.product_id=?");
        $q->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0),$start,$end,$productId]);
        return (int)$q->fetchColumn();
    }catch(Throwable $e){return 0;}
}

/* ---------- Daten ---------- */

function yw_exchange_requests_for_orders(array $orderIds): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$orderIds))));
    if(!$ids || !yw_orders_schema_ready())return [];
    $q=db()->prepare('SELECT * FROM yw_exchange_requests WHERE order_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY id');
    $q->execute($ids);$map=[];
    foreach($q as $row)$map[(int)$row['order_item_id']][]=$row;
    return $map;
}

/** Vollständiger Antrag mit Bestell-, Firmen- und Personendaten. */
function yw_exchange_load(int $id,bool $lock=false): ?array {
    $q=db()->prepare("SELECT r.*,c.slug,c.legal_name,c.shop_name,c.approval_required,o.status order_status,o.created_at order_created_at,o.external_reference,
        i.product_id,i.product_name,i.decoration_snapshot_json,i.unit_net_cents,i.unit_gross_cents,i.vat_rate,i.quantity item_quantity,
        COALESCE(ea.name,'Unbekannt') employee_name,ea.email employee_email,d.name decided_by_name
        FROM yw_exchange_requests r JOIN companies c ON c.id=r.company_id JOIN orders o ON o.id=r.order_id AND o.company_id=r.company_id
        JOIN order_items i ON i.id=r.order_item_id AND i.order_id=r.order_id
        LEFT JOIN employee_accounts ea ON ea.id=r.employee_account_id LEFT JOIN employee_accounts d ON d.id=r.decided_by_account_id
        WHERE r.id=? LIMIT 1".($lock?' FOR UPDATE':''));
    $q->execute([$id]);
    return $q->fetch()?:null;
}

/* ---------- Mitarbeitende: Antrag stellen ---------- */

function yw_exchange_style(): string {
    static $done=false;
    if($done)return '';
    $done=true;
    return '<style>.yw-x{margin:0 20px 18px;padding:14px 16px;border:1px solid var(--cp-border,#e3e8ef);border-radius:6px;background:#fbfcfd}.yw-x-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px 14px;margin-bottom:8px}.yw-x-head strong{font-size:.95rem}.yw-x-head small{color:var(--cp-muted,#667085)}.yw-x-item{padding:10px 0;border-top:1px solid #eef1f5}.yw-x-item:first-of-type{border-top:0}.yw-x-line{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px}.yw-x-req{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;margin-top:7px;font-size:.86rem;color:var(--cp-muted,#667085)}.yw-x-actions{display:flex;flex-wrap:wrap;gap:8px}.yw-x-actions{margin-top:8px}.yw-x-actions details{flex:0 0 auto}.yw-x-actions details[open]{flex:1 1 100%}.yw-x-actions summary{cursor:pointer;display:inline-flex;align-items:center;min-height:36px;padding:0 12px;border:1px solid var(--cp-border,#d8dee8);border-radius:4px;background:#fff;font-weight:800;font-size:.86rem}.yw-x-actions details[open] summary{border-color:var(--cp-accent,#ff6b00)}.yw-x-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px;padding:12px;border:1px solid var(--cp-border,#e3e8ef);border-radius:5px;background:#fff}.yw-x-form label{display:grid;gap:5px;font-size:.8rem;font-weight:750;color:var(--cp-muted,#667085)}.yw-x-form .full{grid-column:1/-1}.yw-x-form select,.yw-x-form input,.yw-x-form textarea{border:1px solid #d8dee8;border-radius:4px;padding:8px 10px;font:inherit;color:#172033;background:#fff}.yw-x-form textarea{min-height:64px}.yw-x-form button{grid-column:1/-1;justify-self:start}.yw-x-badge{font-size:.72rem}.yw-x .badge{display:inline-flex;border-radius:999px;padding:4px 8px;background:#edf1f5;font-weight:800}.yw-x .badge.pending{background:#fff3d9;color:#8a5d00}.yw-x .badge.approved{background:#e6f7ef;color:#126a49}.yw-x .badge.rejected{background:#ffecec;color:#a22820}.yw-x-mgr{display:grid;gap:12px;margin-bottom:22px}.yw-x-mgr .yw-x{margin:0;background:#fff}.yw-x-decide{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:9px;align-items:end;margin-top:10px}.yw-x-decide label{display:grid;gap:5px;font-size:.8rem;font-weight:750;color:var(--cp-muted,#667085)}.yw-x-decide textarea{min-height:52px;border:1px solid #d8dee8;border-radius:4px;padding:8px;font:inherit}@media(max-width:640px){.yw-x{margin:0 14px 16px;padding:12px}.yw-x-form,.yw-x-decide{grid-template-columns:1fr}.yw-x-actions details[open]{flex-basis:100%}}</style>';
}

/** Bereich „Umtausch & Rückgabe“ unter einer eigenen, freigegebenen Bestellung. */
function yw_exchange_order_panel(array $company,array $account,array $order,array $items): string {
    if(!yw_orders_schema_ready() || (string)$order['status']!=='approved' || (int)($order['employee_account_id']??0)!==(int)$account['id'] || !$items)return '';
    $requests=yw_exchange_requests_for_orders([(int)$order['id']]);
    $days=(int)($company['exchange_window_days']??30);
    $deadline=yw_exchange_deadline(yw_exchange_window_base($order),$days);
    $open=yw_exchange_window_open($company,$order);
    if(!$open && !$requests)return '';
    $sizesByProduct=[];
    $productIds=array_values(array_unique(array_map(fn($i)=>(int)$i['product_id'],$items)));
    if($productIds){$q=db()->prepare('SELECT id,sizes FROM products WHERE id IN ('.implode(',',array_fill(0,count($productIds),'?')).')');$q->execute($productIds);foreach($q as $p)$sizesByProduct[(int)$p['id']]=json_decode((string)$p['sizes'],true)?:[];}
    $approval=(int)($company['approval_required']??1)===1;
    $html=yw_exchange_style().'<section class="yw-x" aria-label="Umtausch und Rückgabe"><div class="yw-x-head"><strong>Umtausch &amp; Rückgabe</strong><small>'
        .($open?'Möglich bis '.e($deadline->format('d.m.Y')).($approval?' · Anträge gehen an deine Verantwortlichen':' · Anträge gehen direkt an die Verwaltung'):'Frist abgelaufen').'</small></div>';
    foreach($items as $item){
        $itemRequests=$requests[(int)$item['id']]??[];
        $reserved=0;foreach($itemRequests as $r)if($r['status']!=='rejected')$reserved+=(int)$r['quantity'];
        $remaining=max(0,(int)$item['quantity']-$reserved);
        if(!$itemRequests && (!$open || $remaining<1))continue;
        $html.='<div class="yw-x-item"><div class="yw-x-line"><span>'.(int)$item['quantity'].'× '.e($item['product_name']).' · Größe '.e($item['size']).'</span></div>';
        foreach($itemRequests as $r){
            $html.='<div class="yw-x-req">'.yw_exchange_badge((string)$r['status']).'<span>'.e(yw_exchange_type_label((string)$r['type'])).' · '.(int)$r['quantity'].' Stück'.($r['type']==='exchange'?' · neue Größe '.e((string)$r['new_size']):'').' · beantragt am '.e(date('d.m.Y',strtotime((string)$r['created_at']))).'</span>'
                .(!empty($r['decision_note'])?'<span>Hinweis: '.e((string)$r['decision_note']).'</span>':'').'</div>';
        }
        if($open && $remaining>0){
            $sizes=array_values(array_filter(array_map('strval',$sizesByProduct[(int)$item['product_id']]??[]),fn($s)=>$s!==(string)$item['size']));
            $qtyField=static function(string $id) use($remaining): string {
                if($remaining===1)return '<input type="hidden" name="quantity" value="1"><label>Menge<input value="1 Stück" disabled id="'.$id.'"></label>';
                $o='';for($n=1;$n<=$remaining;$n++)$o.='<option value="'.$n.'">'.$n.' Stück</option>';
                return '<label for="'.$id.'">Menge<select name="quantity" id="'.$id.'">'.$o.'</select></label>';
            };
            $base=csrf_field().'<input type="hidden" name="action" value="exchange_request"><input type="hidden" name="order_item_id" value="'.(int)$item['id'].'">';
            $html.='<div class="yw-x-actions">';
            if($sizes){
                $html.='<details><summary>Größe tauschen</summary><form method="post" class="yw-x-form">'.$base.'<input type="hidden" name="type" value="exchange"><label>Neue Größe<select name="new_size" required>';
                foreach($sizes as $s)$html.='<option value="'.e($s).'">Größe '.e($s).'</option>';
                $html.='</select></label>'.$qtyField('xq-e-'.(int)$item['id']).'<label class="full">Grund<textarea name="reason" maxlength="500" minlength="3" required placeholder="z. B. fällt zu klein aus"></textarea></label><button class="cp-btn approve" type="submit">Tausch beantragen</button></form></details>';
            }
            $html.='<details><summary>Zurückgeben</summary><form method="post" class="yw-x-form">'.$base.'<input type="hidden" name="type" value="return">'.$qtyField('xq-r-'.(int)$item['id']).'<label class="full">Grund<textarea name="reason" maxlength="500" minlength="3" required placeholder="z. B. doppelt bestellt"></textarea></label><button class="cp-btn approve" type="submit">Rückgabe beantragen</button></form></details>';
            $html.='</div>';
        }
        $html.='</div>';
    }
    return $html.'</section>';
}

/** POST-Aktionen im Firmenshop (exchange_request, exchange_decision). */
function yw_exchange_portal_action(array $company,array $account,string $action,string $section): void {
    $slug=(string)$company['slug'];
    if(!yw_orders_schema_ready())return;
    if($action==='exchange_request' && $section==='bestellungen'){
        [$type,$message]=yw_exchange_create($company,$account,(int)($_POST['order_item_id']??0),(string)($_POST['type']??''),(string)($_POST['new_size']??''),(string)($_POST['quantity']??'1'),is_string($_POST['reason']??null)?$_POST['reason']:'');
        flash($type,$message);redirect('/'.$slug.'/bestellungen');
    }
    if($action==='exchange_decision' && $section==='freigaben' && account_is_manager($account)){
        [$type,$message]=yw_exchange_manager_decide($company,$account,(int)($_POST['exchange_id']??0),(string)($_POST['decision']??''),is_string($_POST['decision_note']??null)?$_POST['decision_note']:'');
        flash($type,$message);redirect('/'.$slug.'/freigaben');
    }
}

/** Legt einen Antrag an. @return array{0:string,1:string} */
function yw_exchange_create(array $company,array $account,int $itemId,string $type,string $newSize,string $quantity,string $reason): array {
    $reason=trim($reason);
    if(!in_array($type,['exchange','return'],true))return ['error','Bitte Tausch oder Rückgabe wählen.'];
    if(mb_strlen($reason)<3||mb_strlen($reason)>500)return ['error','Bitte einen Grund angeben (3 bis 500 Zeichen).'];
    if(!preg_match('/^[1-9]\d?$/D',$quantity))return ['error','Menge ungültig.'];
    $qty=(int)$quantity;
    try{
        db()->beginTransaction();
        $q=db()->prepare('SELECT * FROM companies WHERE id=? AND active=1 LIMIT 1');$q->execute([(int)$company['id']]);$company=$q->fetch();
        if(!$company)throw new DomainException('Firmenshop nicht verfügbar.');
        // Position muss zu einer eigenen, freigegebenen Bestellung dieser Firma gehören.
        $q=db()->prepare("SELECT i.*,o.status order_status,o.fulfillment_status,o.production_updated_at,o.decided_at,o.created_at order_created_at,o.employee_account_id FROM order_items i JOIN orders o ON o.id=i.order_id WHERE i.id=? AND o.company_id=? AND o.employee_account_id=? FOR UPDATE");
        $q->execute([$itemId,(int)$company['id'],(int)$account['id']]);$item=$q->fetch();
        if(!$item)throw new DomainException('Position nicht gefunden.');
        $order=['status'=>$item['order_status'],'fulfillment_status'=>$item['fulfillment_status'],'production_updated_at'=>$item['production_updated_at'],'decided_at'=>$item['decided_at'],'created_at'=>$item['order_created_at']];
        if(!yw_exchange_window_open($company,$order))throw new DomainException('Für diese Bestellung ist kein Umtausch und keine Rückgabe (mehr) möglich.');
        $q=db()->prepare("SELECT COALESCE(SUM(quantity),0) FROM yw_exchange_requests WHERE order_item_id=? AND status<>'rejected'");$q->execute([$itemId]);
        $prior=(int)$q->fetchColumn();
        if($qty>(int)$item['quantity']-$prior)throw new DomainException('Für diese Menge besteht bereits ein Antrag.');
        // 10.11: Gutschrift aus dem Positionswert (Rundung je Position); Altbestellungen: Stückwert.
        $lineCompany=yw_order_item_amounts($item)['company'];$itemQty=max(1,(int)$item['quantity']);
        $unit=intdiv($lineCompany,$itemQty);
        $delta=-(intdiv($lineCompany*($prior+$qty),$itemQty)-intdiv($lineCompany*$prior,$itemQty));
        $newSizeValue=null;
        if($type==='exchange'){
            $q=db()->prepare('SELECT p.*,cp.price_override_cents FROM products p LEFT JOIN company_products cp ON cp.product_id=p.id AND cp.company_id=? WHERE p.id=? LIMIT 1');
            $q->execute([(int)$company['id'],(int)$item['product_id']]);$product=$q->fetch();
            $sizes=$product?array_map('strval',json_decode((string)$product['sizes'],true)?:[]):[];
            if(!in_array($newSize,$sizes,true)||$newSize===(string)$item['size'])throw new DomainException('Bitte eine andere, verfügbare Größe wählen.');
            $newSizeValue=$newSize;
            $delta=(yw_exchange_unit_company_cents($product,$company,$newSize)-yw_exchange_unit_company_cents($product,$company,(string)$item['size']))*$qty;
            if($delta>0){
                [$start,$end]=yw_period((int)$company['budget_reset_month'],new DateTimeImmutable((string)$item['order_created_at']));
                $limit=yw_budget_limit($company,$account);
                if($limit!==null){
                    // Mehrkosten belasten das Budgetjahr der ursprünglichen Bestellung.
                    $u=db()->prepare("SELECT COALESCE(SUM(company_total_cents),0) FROM orders WHERE company_id=? AND (employee_account_id=? OR (employee_account_id IS NULL AND invite_id=?)) AND status IN ('pending','approved') AND created_at>=? AND created_at<?");
                    $u->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0),$start,$end]);
                    yw_check_limits($limit,(int)$u->fetchColumn()+yw_exchange_budget_adjustment($company,$account,$start,$end),$delta);
                }
            }
        }
        $status=(int)$company['approval_required']===1?'requested':'approved';
        db()->prepare('INSERT INTO yw_exchange_requests(company_id,order_id,order_item_id,employee_account_id,type,quantity,old_size,new_size,reason,status,unit_company_cents,budget_delta_cents,decided_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([(int)$company['id'],(int)$item['order_id'],$itemId,(int)$account['id'],$type,$qty,(string)$item['size'],$newSizeValue,$reason,$status,$unit,$delta,$status==='approved'?date('Y-m-d H:i:s'):null]);
        $id=(int)db()->lastInsertId();
        db()->commit();
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        if(!$e instanceof DomainException)error_log('WorkwearCenter exchange create: '.$e->getMessage());
        return ['error',$e instanceof DomainException?$e->getMessage():'Antrag konnte nicht gespeichert werden.'];
    }
    audit_log('exchange.requested','order',(int)$item['order_id'],['company_id'=>(int)$company['id'],'exchange_id'=>$id,'type'=>$type,'status'=>$status]);
    if($status==='approved')yw_exchange_handover_enqueue($id);
    yw_exchange_notify($id,'created');
    return ['success',yw_exchange_type_label($type).' beantragt. '.($status==='requested'?'Deine Verantwortlichen entscheiden darüber.':'Die Verwaltung meldet sich mit den nächsten Schritten.')];
}

/* ---------- Verantwortliche ---------- */

function yw_exchange_manager_panel(array $company,array $account): string {
    if(!yw_orders_schema_ready())return '';
    $q=db()->prepare("SELECT r.*,i.product_name,COALESCE(ea.name,'Unbekannt') employee_name FROM yw_exchange_requests r JOIN order_items i ON i.id=r.order_item_id LEFT JOIN employee_accounts ea ON ea.id=r.employee_account_id WHERE r.company_id=? AND r.status='requested' ORDER BY r.id");
    $q->execute([(int)$company['id']]);$rows=$q->fetchAll();
    if(!$rows)return '';
    $others=yw_other_manager_count((int)$company['id'],(int)$account['id']);
    $html=yw_exchange_style().'<section class="yw-x-mgr" aria-label="Umtausch und Rückgabe"><div class="cp-manager-alert"><strong>'.count($rows).' Antrag'.(count($rows)===1?'':'e').' auf Umtausch oder Rückgabe</strong> warte'.(count($rows)===1?'t':'n').' auf deine Entscheidung.</div>';
    foreach($rows as $r){
        $html.='<article class="yw-x"><div class="yw-x-head"><strong>'.e(yw_exchange_type_label((string)$r['type'])).' · Bestellung #'.(int)$r['order_id'].' · '.e($r['employee_name']).'</strong><small>'.e(date('d.m.Y H:i',strtotime((string)$r['created_at']))).' Uhr</small></div>';
        $html.='<div class="yw-x-line"><span>'.(int)$r['quantity'].'× '.e($r['product_name']).' · Größe '.e($r['old_size']).($r['type']==='exchange'?' → <strong>'.e((string)$r['new_size']).'</strong>':'').'</span><span>'.((int)$r['budget_delta_cents']!==0?'Budget '.((int)$r['budget_delta_cents']>0?'+':'').money((int)$r['budget_delta_cents']).($r['type']==='return'?' nach Abschluss':''):'budgetneutral').'</span></div>';
        $html.='<div class="yw-x-req"><span>Grund: '.e((string)$r['reason']).'</span></div>';
        if((int)$r['employee_account_id']===(int)$account['id'] && $others>0){$html.='<div class="yw-x-req"><span>Eigener Antrag – entscheidet eine andere verantwortliche Person.</span></div></article>';continue;}
        $html.='<form method="post" class="yw-x-decide">'.csrf_field().'<input type="hidden" name="action" value="exchange_decision"><input type="hidden" name="exchange_id" value="'.(int)$r['id'].'"><label>Hinweis <small>bei Ablehnung erforderlich</small><textarea name="decision_note" maxlength="1000"></textarea></label><button class="cp-btn reject" type="submit" name="decision" value="rejected">Ablehnen</button><button class="cp-btn approve" type="submit" name="decision" value="approved">Freigeben</button></form></article>';
    }
    return $html.'</section>';
}

/** @return array{0:string,1:string} */
function yw_exchange_manager_decide(array $company,array $account,int $id,string $decision,string $note): array {
    $note=trim($note);
    if(!in_array($decision,['approved','rejected'],true))return ['error','Ungültige Entscheidung.'];
    if(mb_strlen($note)>1000||($decision==='rejected'&&$note===''))return ['error',$decision==='rejected'?'Bitte einen kurzen Ablehnungsgrund angeben.':'Der Hinweis darf höchstens 1.000 Zeichen enthalten.'];
    $q=db()->prepare('SELECT employee_account_id FROM yw_exchange_requests WHERE id=? AND company_id=? LIMIT 1');$q->execute([$id,(int)$company['id']]);$owner=$q->fetchColumn();
    if($owner===false)return ['error','Antrag nicht gefunden.'];
    $self=(int)$owner===(int)$account['id'];
    if($self && $decision==='approved' && yw_other_manager_count((int)$company['id'],(int)$account['id'])>0)return ['error','Eigene Anträge gibt eine andere verantwortliche Person frei (Vier-Augen-Prinzip).'];
    $u=db()->prepare("UPDATE yw_exchange_requests SET status=?,decision_note=?,decided_by_account_id=?,decided_at=NOW() WHERE id=? AND company_id=? AND status='requested'");
    $u->execute([$decision,$note!==''?$note:null,(int)$account['id'],$id,(int)$company['id']]);
    if(!$u->rowCount())return ['error','Der Antrag wurde bereits bearbeitet.'];
    $orderId=(int)db()->query('SELECT order_id FROM yw_exchange_requests WHERE id='.(int)$id)->fetchColumn();
    audit_log('exchange.'.$decision,'order',$orderId,['company_id'=>(int)$company['id'],'exchange_id'=>$id]+($self?['self_decision'=>true]:[]));
    if($decision==='approved')yw_exchange_handover_enqueue($id);
    yw_exchange_notify($id,$decision);
    return ['success',$decision==='approved'?'Antrag freigegeben. Die Verwaltung übernimmt.':'Antrag abgelehnt.'];
}

/* ---------- Benachrichtigungen ---------- */

function yw_exchange_notify(int $id,string $event): void {
    try{
        $r=yw_exchange_load($id);if(!$r)return;
        $type=yw_exchange_type_label((string)$r['type']);
        $line=(int)$r['quantity'].' × '.$r['product_name'].' · Größe '.$r['old_size'].($r['type']==='exchange'?' → '.$r['new_size']:'');
        $facts="Antrag: ".$type." #".$id."\nBestellung: ".yw_order_number((int)$r['order_id'])."\nMitarbeiter: ".$r['employee_name']."\nPosition: ".$line."\nGrund: ".$r['reason']
            .((int)$r['budget_delta_cents']!==0?"\nBudget: ".((int)$r['budget_delta_cents']>0?'+':'').money((int)$r['budget_delta_cents']).($r['type']==='return'?' (Gutschrift nach Abschluss)':''):'');
        $status=yw_exchange_statuses()[(string)$r['status']]??$r['status'];
        $shopLink="\n\nBestellungen ansehen: ".workwearcenter_url((string)$r['slug'].'/bestellungen');
        $adminLink="\n\nUmtausch & Retouren: ".workwearcenter_url('admin?tab=exchanges');
        $note=!empty($r['decision_note'])?"\nHinweis: ".$r['decision_note']:'';
        $toEmployee=function(string $subject,string $text) use($r){if(!empty($r['employee_email']))send_system_mail((string)$r['employee_email'],$subject,$text);};
        $toAdmins=function(string $subject,string $text){foreach(db()->query('SELECT email FROM admins WHERE active=1') as $a)send_system_mail((string)$a['email'],$subject,$text);};
        $subject=$type.' #'.$id.' · '.$status;
        if($event==='created'){
            $toEmployee($type.' beantragt',"Hallo ".$r['employee_name'].",\n\ndein Antrag ist eingegangen.\n\n".$facts."\nStatus: ".$status.$shopLink);
            if($r['status']==='requested')notify_company_managers((int)$r['company_id'],$type.' zur Freigabe: '.yw_order_number((int)$r['order_id']),"Ein Antrag auf ".$type." wartet auf deine Entscheidung.\n\n".$facts."\n\nAntrag prüfen: ".workwearcenter_url((string)$r['slug'].'/freigaben'));
            else $toAdmins('Neuer Antrag: '.$type.' #'.$id,"Ein neuer Antrag ist ohne interne Freigabe eingegangen.\n\nFirma: ".$r['legal_name']."\n".$facts.$adminLink);
            return;
        }
        $toEmployee($subject,"Hallo ".$r['employee_name'].",\n\nneuer Stand deines Antrags: ".$status.".".$note."\n\n".$facts.$shopLink);
        if($event==='approved' && $r['decided_by_account_id'])$toAdmins('Freigegeben: '.$type.' #'.$id,"Ein Antrag wurde von ".$r['decided_by_name']." freigegeben und kann bearbeitet werden.\n\nFirma: ".$r['legal_name']."\n".$facts.$adminLink);
        if(in_array($event,['admin_approved','done','admin_rejected'],true))notify_company_managers((int)$r['company_id'],$subject,"Neuer Stand eines Antrags: ".$status.".".$note."\n\n".$facts);
    }catch(Throwable $e){error_log('WorkwearCenter exchange mail: '.$e->getMessage());}
}

/* ---------- Übergabe an die Auftragszentrale ---------- */

function yw_exchange_handover_event(int $id): string { return 'exchange.approved:'.$id; }

/** Datensatz workwearcenter.exchange.v1 für einen freigegebenen Tausch. */
function yw_exchange_payload(int $id,string $deliveryId=''): array {
    $r=yw_exchange_load($id);
    if(!$r)throw new RuntimeException('Antrag '.$id.' nicht gefunden.');
    $order=yw_handover_payload((int)$r['order_id'],'order.snapshot','');
    $line=null;
    foreach(order_items((int)$r['order_id']) as $n=>$it)if((int)$it['id']===(int)$r['order_item_id'])$line=$n+1;
    $item=null;foreach($order['items'] as $candidate)if((int)$candidate['line']===$line)$item=$candidate;
    $iso=static fn($v)=>$v?(new DateTimeImmutable((string)$v))->format(DATE_ATOM):null;
    return [
        'schema'=>YW_EXCHANGE_SCHEMA,
        'event'=>'exchange.approved',
        'delivery_id'=>$deliveryId,
        'generated_at'=>(new DateTimeImmutable())->format(DATE_ATOM),
        'source'=>$order['source'],
        'exchange'=>[
            'id'=>$id,
            'number'=>yw_order_number((int)$r['order_id']).'-T'.$id,
            'type'=>(string)$r['type'],
            'status'=>(string)$r['status'],
            'reason'=>(string)$r['reason'],
            'requested_at'=>$iso($r['created_at']),
            'approved_at'=>$iso($r['decided_at']),
            'approved_by'=>$r['decided_by_name']?['id'=>(int)$r['decided_by_account_id'],'name'=>(string)$r['decided_by_name']]:null,
            'quantity'=>(int)$r['quantity'],
            'old_size'=>(string)$r['old_size'],
            'new_size'=>(string)$r['new_size'],
            'budget_delta_cents'=>(int)$r['budget_delta_cents'],
        ],
        'order'=>['id'=>(int)$r['order_id'],'number'=>yw_order_number((int)$r['order_id']),'external_reference'=>$r['external_reference']?:null,'line'=>$line],
        'customer'=>$order['customer'],
        'employee'=>$order['employee'],
        'delivery'=>$order['delivery'],
        'item'=>$item?array_merge($item,['quantity'=>(int)$r['quantity'],'size'=>(string)$r['new_size'],'original_size'=>(string)$r['old_size']]):null,
    ];
}

/** Übergabedatensatz für einen freigegebenen Tausch (Rückgaben brauchen keine Produktion). */
function yw_exchange_handover_enqueue(int $id): void {
    try{
        $r=yw_exchange_load($id);
        if(!$r || $r['type']!=='exchange')return;
        $deliveryId=bin2hex(random_bytes(16));
        $json=json_encode(yw_exchange_payload($id,$deliveryId),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        // Format „auftragszentrale“: kein automatischer Versand, bis die Gegenseite Tauschaufträge kennt.
        $status=yw_handover_config()['format']==='auftragszentrale'?'manual':'pending';
        db()->prepare('INSERT IGNORE INTO yw_handover(order_id,event,delivery_id,payload_json,status) VALUES(?,?,?,?,?)')
            ->execute([(int)$r['order_id'],yw_exchange_handover_event($id),$deliveryId,$json,$status]);
    }catch(Throwable $e){error_log('WorkwearCenter exchange handover #'.$id.': '.$e->getMessage());}
}

function yw_exchange_handover_map(array $ids): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));
    if(!$ids)return [];
    $events=array_map('yw_exchange_handover_event',$ids);
    $q=db()->prepare('SELECT id,event,status,delivered_at,acknowledged_at,last_error,external_reference FROM yw_handover WHERE event IN ('.implode(',',array_fill(0,count($events),'?')).')');
    $q->execute($events);$map=[];
    foreach($q as $row)$map[(int)substr((string)$row['event'],strlen('exchange.approved:'))]=$row;
    return $map;
}

/* ---------- Verwaltung ---------- */

function yw_admin_exchange_open_count(): int {
    if(!yw_orders_schema_ready())return 0;
    try{return (int)db()->query("SELECT COUNT(*) FROM yw_exchange_requests WHERE status IN ('approved','received')")->fetchColumn();}catch(Throwable $e){return 0;}
}

function yw_admin_exchanges(): void {
    if(!yw_orders_schema_ready()){echo '<div class="flash info">Bitte zuerst den Update-Assistenten ausführen (Datenbankstand 10.11).</div>';return;}
    $filter=(string)($_GET['status']??'offen');
    $where=match($filter){'offen'=>"r.status IN ('requested','approved','received')",'alle'=>'1=1',default=>array_key_exists($filter,yw_exchange_statuses())?'r.status='.db()->quote($filter):"r.status IN ('requested','approved','received')"};
    $counts=array_fill_keys(array_keys(yw_exchange_statuses()),0);
    foreach(db()->query('SELECT status,COUNT(*) n FROM yw_exchange_requests GROUP BY status') as $c)$counts[(string)$c['status']]=(int)$c['n'];
    $rows=db()->query("SELECT r.*,c.legal_name,i.product_name,COALESCE(ea.name,'Unbekannt') employee_name FROM yw_exchange_requests r JOIN companies c ON c.id=r.company_id JOIN order_items i ON i.id=r.order_item_id LEFT JOIN employee_accounts ea ON ea.id=r.employee_account_id WHERE $where ORDER BY FIELD(r.status,'approved','received','requested','done','rejected'),r.id DESC LIMIT 200")->fetchAll();
    $handover=yw_exchange_handover_map(array_column($rows,'id'));
    echo yw_exchange_admin_style();
    echo '<section class="card"><div class="company-kpi-grid yw-x-kpis">';
    foreach(yw_exchange_statuses() as $k=>$l)echo '<a class="company-kpi" href="/admin?tab=exchanges&status='.e($k).'"><strong>'.(int)$counts[$k].'</strong><span>'.e($l).'</span></a>';
    echo '</div><nav class="admin-subtabs">';
    foreach(['offen'=>'Offen','alle'=>'Alle']+yw_exchange_statuses() as $k=>$l)echo '<a class="'.($filter===$k?'active':'').'" href="/admin?tab=exchanges&status='.e($k).'">'.e($l).'</a>';
    echo '</nav><p class="muted">Freigegebene Tauschanträge werden als eigener Übergabedatensatz geführt. Im Format „Auftragszentrale“ bitte manuell übertragen und anschließend als übertragen markieren.</p></section>';
    echo '<section class="card"><h2>Umtausch & Retouren</h2>';
    if(!$rows){echo '<div class="empty compact">Keine Anträge in dieser Ansicht.</div></section>';return;}
    echo '<div class="yw-x-admin-list">';
    foreach($rows as $r)echo yw_exchange_admin_row($r,$handover[(int)$r['id']]??null,'exchanges');
    echo '</div></section>';
}

function yw_exchange_admin_style(): string {
    static $done=false;if($done)return '';$done=true;
    return '<style>.yw-x-kpis{grid-template-columns:repeat(5,minmax(0,1fr))!important}.yw-x-kpis a{text-decoration:none;color:inherit}.yw-x-admin p{font-size:13px}.yw-x-admin-list{display:grid;gap:12px}.yw-x-admin{border:1px solid #e4e8ef;border-radius:14px;padding:14px 16px;background:#fff}.yw-x-admin-head{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px 14px}.yw-x-admin-head h3{margin:0;font-size:15px}.yw-x-admin-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:12px 0}.yw-x-admin-grid div{background:#f6f8fa;border-radius:10px;padding:9px 11px;font-size:13px}.yw-x-admin-grid span{display:block;color:#737d8e;font-size:11px;margin-bottom:3px}.yw-x-admin form.yw-x-admin-form{display:grid;grid-template-columns:minmax(0,1fr) 150px;gap:10px;align-items:end}.yw-x-admin-form .actions{grid-column:1/-1}@media(max-width:760px){.yw-x-kpis{grid-template-columns:repeat(2,minmax(0,1fr))!important}.yw-x-admin-grid{grid-template-columns:1fr 1fr}.yw-x-admin form.yw-x-admin-form{grid-template-columns:1fr}}</style>';
}

function yw_exchange_admin_row(array $r,?array $handover,string $returnTab): string {
    $status=(string)$r['status'];
    $html='<article class="yw-x-admin" id="antrag-'.(int)$r['id'].'"><div class="yw-x-admin-head"><h3>'.e(yw_exchange_type_label((string)$r['type'])).' #'.(int)$r['id'].' · <a href="'.e(yw_order_url((int)$r['order_id'])).'">'.e(yw_order_number((int)$r['order_id'])).'</a></h3><div>'.yw_exchange_badge($status).'</div></div>';
    $html.='<div class="yw-x-admin-grid"><div><span>Firma · Person</span>'.e($r['legal_name']??'').' · '.e($r['employee_name']).'</div><div><span>Position</span>'.(int)$r['quantity'].'× '.e($r['product_name']).' · '.e($r['old_size']).($r['type']==='exchange'?' → <strong>'.e((string)$r['new_size']).'</strong>':'').'</div><div><span>Budget</span>'.((int)$r['budget_delta_cents']===0?'neutral':((int)$r['budget_delta_cents']>0?'+':'').money((int)$r['budget_delta_cents'])).'</div><div><span>Beantragt</span>'.e(date('d.m.Y H:i',strtotime((string)$r['created_at']))).'</div></div>';
    $html.='<p class="muted">Grund: '.e((string)$r['reason']).(!empty($r['decision_note'])?'<br>Hinweis: '.e((string)$r['decision_note']):'').'</p>';
    if($r['type']==='exchange'){
        if($handover){
            [$label,$class]=yw_handover_status_label((string)$handover['status']);
            $html.='<p>Übergabe: <span class="badge '.e($class).'">'.e($label).'</span>'.(!empty($handover['acknowledged_at'])?' <span class="muted">am '.e(date('d.m.Y',strtotime((string)$handover['acknowledged_at']))).'</span>':'').'</p>';
            if((string)$handover['status']==='manual')$html.='<form method="post" class="actions">'.csrf_field().'<input type="hidden" name="action" value="exchange_handover_done"><input type="hidden" name="handover_id" value="'.(int)$handover['id'].'"><input type="hidden" name="return_tab" value="'.e($returnTab).'"><input name="reference" maxlength="120" placeholder="Auftragsnummer in der Auftragszentrale (optional)" style="max-width:320px"><button class="btn small">Als übertragen markieren</button><a class="btn small" href="/admin?tab=handover">Daten ansehen</a></form>';
        }elseif(in_array($status,['requested'],true))$html.='<p class="muted">Übergabe an die Auftragszentrale nach Freigabe.</p>';
    }
    $next=array_values(array_filter(['approved','received','done','rejected'],fn($to)=>yw_exchange_transition_allowed($status,$to)));
    if($next){
        $labels=['approved'=>'Freigeben','received'=>'Ware erhalten','done'=>'Erledigt','rejected'=>'Ablehnen'];
        $html.='<form method="post" class="yw-x-admin-form">'.csrf_field().'<input type="hidden" name="action" value="exchange_status"><input type="hidden" name="exchange_id" value="'.(int)$r['id'].'"><input type="hidden" name="return_tab" value="'.e($returnTab).'">'
            .'<label class="field">Hinweis an die Person <small>bei Ablehnung erforderlich</small><input name="note" maxlength="1000"></label>'
            .'<label class="field">Budgetwirkung (€)<input name="budget_delta" inputmode="decimal" value="'.e(number_format((int)$r['budget_delta_cents']/100,2,',','')).'" title="Negativ = Gutschrift, positiv = Belastung"></label><div class="actions">';
        foreach($next as $to)$html.='<button class="btn small'.($to==='rejected'?' danger':($to==='done'?' primary':'')).'" name="to" value="'.e($to).'">'.e($labels[$to]).'</button>';
        $html.='</div></form>';
    }
    return $html.'</article>';
}

/** Statuswechsel und manuelle Übertragung durch die Verwaltung. */
function yw_admin_exchange_action(string $action): never {
    $back='/admin?tab='.(((string)($_POST['return_tab']??''))==='orders'?'orders':'exchanges');
    $r=null;
    try{
        if(!yw_orders_schema_ready())throw new RuntimeException('Bitte zuerst den Update-Assistenten ausführen.');
        if($action==='exchange_handover_done'){
            $id=(int)($_POST['handover_id']??0);
            $reference=mb_substr(trim((string)($_POST['reference']??'')),0,120);
            $u=db()->prepare("UPDATE yw_handover SET status='acknowledged',acknowledged_at=NOW(),last_error=NULL,external_reference=COALESCE(NULLIF(?,''),external_reference) WHERE id=? AND status='manual' AND event LIKE 'exchange.%'");
            $u->execute([$reference,$id]);
            if(!$u->rowCount())throw new RuntimeException('Übergabe nicht gefunden oder bereits erledigt.');
            audit_log('exchange.handover_manual','handover',$id,['reference'=>$reference!==''?$reference:null]);
            flash('success','Als manuell übertragen markiert.');
            if($back==='/admin?tab=orders'){$q=db()->prepare('SELECT order_id FROM yw_handover WHERE id=?');$q->execute([$id]);$back=yw_order_url((int)$q->fetchColumn());}
            redirect($back);
        }
        if($action!=='exchange_status')throw new RuntimeException('Aktion unbekannt.');
        $id=(int)($_POST['exchange_id']??0);$to=(string)($_POST['to']??'');$note=trim((string)($_POST['note']??''));
        if(mb_strlen($note)>1000)throw new RuntimeException('Hinweis: höchstens 1.000 Zeichen.');
        if($to==='rejected'&&$note==='')throw new RuntimeException('Bitte einen Ablehnungsgrund angeben.');
        $rawDelta=trim((string)($_POST['budget_delta']??''));
        $delta=null;
        if($rawDelta!==''){
            if(!preg_match('/^(-)?(\d{1,6})(?:[.,](\d{1,2}))?$/D',$rawDelta,$m))throw new RuntimeException('Budgetwirkung bitte als Betrag in Euro, z. B. -24,90.');
            $delta=((int)$m[2]*100+(int)str_pad($m[3]??'',2,'0'))*(($m[1]??'')==='-'?-1:1);
        }
        db()->beginTransaction();
        $r=yw_exchange_load($id,true);
        if(!$r)throw new RuntimeException('Antrag nicht gefunden.');
        if(!yw_exchange_transition_allowed((string)$r['status'],$to))throw new RuntimeException('Dieser Statuswechsel ist nicht möglich.');
        $set=['status=?'];$params=[$to];
        if($note!==''){$set[]='decision_note=?';$params[]=$note;}
        if($delta!==null){$set[]='budget_delta_cents=?';$params[]=$delta;}
        if($to==='approved'||$to==='rejected'){$set[]='decided_at=COALESCE(decided_at,NOW())';}
        if($to==='received')$set[]='received_at=NOW()';
        if($to==='done')$set[]='completed_at=NOW()';
        $params[]=$id;$params[]=(string)$r['status'];
        db()->prepare('UPDATE yw_exchange_requests SET '.implode(',',$set).' WHERE id=? AND status=?')->execute($params);
        // Abgelehnte Tauschanträge nicht mehr übergeben.
        if($to==='rejected')db()->prepare("UPDATE yw_handover SET status='cancelled',last_error='Antrag abgelehnt' WHERE event=? AND status IN ('pending','failed','manual')")->execute([yw_exchange_handover_event($id)]);
        db()->commit();
        audit_log('exchange.status','order',(int)$r['order_id'],['company_id'=>(int)$r['company_id'],'exchange_id'=>$id,'from'=>$r['status'],'to'=>$to]+($delta!==null&&$delta!==(int)$r['budget_delta_cents']?['budget_delta_cents'=>$delta]:[]));
        if($to==='approved')yw_exchange_handover_enqueue($id);
        yw_exchange_notify($id,match($to){'approved'=>'admin_approved','rejected'=>'admin_rejected',default=>$to});
        flash('success','Antrag #'.$id.': '.yw_exchange_statuses()[$to].'.');
        if($back==='/admin?tab=orders')$back=yw_order_url((int)$r['order_id']);
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        if(!$e instanceof RuntimeException)error_log('Exchange admin: '.$e->getMessage());
        flash('error',$e instanceof RuntimeException?$e->getMessage():'Aktion fehlgeschlagen.');
        if($back==='/admin?tab=orders' && !empty($r['order_id']))$back=yw_order_url((int)$r['order_id']);
    }
    redirect($back);
}

/** Karte auf der Auftragsseite der Verwaltung. */
function yw_exchange_admin_order_card(int $orderId): void {
    if(!yw_orders_schema_ready())return;
    $q=db()->prepare("SELECT r.*,c.legal_name,i.product_name,COALESCE(ea.name,'Unbekannt') employee_name FROM yw_exchange_requests r JOIN companies c ON c.id=r.company_id JOIN order_items i ON i.id=r.order_item_id LEFT JOIN employee_accounts ea ON ea.id=r.employee_account_id WHERE r.order_id=? ORDER BY r.id");
    $q->execute([$orderId]);$rows=$q->fetchAll();
    if(!$rows)return;
    $handover=yw_exchange_handover_map(array_column($rows,'id'));
    echo yw_exchange_admin_style().'<section class="card"><h2>Umtausch & Retouren</h2><div class="yw-x-admin-list">';
    foreach($rows as $r)echo yw_exchange_admin_row($r,$handover[(int)$r['id']]??null,'orders');
    echo '</div></section>';
}
