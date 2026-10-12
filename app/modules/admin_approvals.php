<?php
declare(strict_types=1);

/** Offene Mitarbeiterregistrierungen, unabhängig von der jeweiligen Firmenakte. */
function yw_admin_approvals_count(): int {
    try {
        return (int)db()->query("SELECT COUNT(*) FROM employee_accounts WHERE registration_status='pending' AND anonymized_at IS NULL")->fetchColumn();
    } catch(Throwable $e) {
        return 0;
    }
}

/** Nur freigegebene Rücksprungziele im selben Verwaltungsbereich übernehmen. */
function yw_admin_approvals_return_url(): string {
    $raw=(string)($_POST['return_url']??'');
    $parts=parse_url($raw);
    if(!is_array($parts) || ($parts['path']??'')!=='/admin' || isset($parts['scheme']) || isset($parts['host']))return '/admin?tab=approvals';
    parse_str((string)($parts['query']??''),$query);
    if(($query['tab']??'')!=='approvals')return '/admin?tab=approvals';
    $safe=['tab'=>'approvals'];
    if(is_string($query['q']??null) && trim($query['q'])!=='')$safe['q']=mb_substr(trim($query['q']),0,100);
    $page=filter_var($query['seite']??null,FILTER_VALIDATE_INT);
    if($page!==false && $page!==null && $page>1)$safe['seite']=min(100000,$page);
    return '/admin?'.http_build_query($safe);
}

function yw_admin_approvals_page(): void {
    $perPage=25;
    $search=trim(mb_substr((string)($_GET['q']??''),0,100));
    $where="ea.registration_status='pending' AND ea.anonymized_at IS NULL";
    $params=[];
    if($search!==''){
        $where.=' AND (ea.name LIKE ? OR ea.email LIKE ? OR c.legal_name LIKE ? OR c.shop_name LIKE ?)';
        $needle='%'.$search.'%';
        $params=[$needle,$needle,$needle,$needle];
    }

    $count=db()->prepare("SELECT COUNT(*) FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE $where");
    $count->execute($params);
    $total=(int)$count->fetchColumn();
    $pages=max(1,(int)ceil($total/$perPage));
    $page=max(1,min($pages,(int)($_GET['seite']??1)));
    $offset=($page-1)*$perPage;
    $query=db()->prepare("SELECT ea.id,ea.name,ea.email,ea.created_at,c.id company_id,c.legal_name,c.shop_name
        FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id
        WHERE $where ORDER BY ea.created_at ASC,ea.id ASC LIMIT $perPage OFFSET $offset");
    $query->execute($params);
    $people=$query->fetchAll();
    $pageUrl=static function(int $target) use($search): string {
        $query=['tab'=>'approvals'];
        if($search!=='')$query['q']=$search;
        if($target>1)$query['seite']=$target;
        return '/admin?'.http_build_query($query);
    };

    echo '<section class="card"><div class="approval-toolbar"><div><h2>Offene Registrierungen <span class="muted">('.$total.')</span></h2><p class="muted">Alle Firmen in einer Liste. Freigaben werden direkt an den Mitarbeiter gemeldet.</p></div>'
        .'<form method="get" class="approval-search" role="search"><input type="hidden" name="tab" value="approvals"><label>Person oder Firma suchen<input type="search" name="q" maxlength="100" value="'.e($search).'" placeholder="Name, E-Mail oder Firmenname"></label><button class="btn" type="submit">Suchen</button>'.($search!==''?'<a class="btn" href="/admin?tab=approvals">Zurücksetzen</a>':'').'</form></div>';

    if(!$people){
        echo '<div class="empty compact">'.($search!==''?'Keine offenen Registrierungen für diese Suche.':'Keine offenen Mitarbeiterfreigaben. Alle Registrierungen sind bearbeitet.').'</div></section>';
        return;
    }

    echo '<div class="approval-list">';
    foreach($people as $person){
        $id=(int)$person['id'];
        $companyId=(int)$person['company_id'];
        $initial=mb_strtoupper(mb_substr(trim((string)$person['name']),0,1));
        $created=strtotime((string)$person['created_at']);
        echo '<article class="approval-card"><div class="approval-person"><span class="approval-avatar" aria-hidden="true">'.e($initial).'</span><div><strong>'.e($person['name']).'</strong><small><a href="mailto:'.e($person['email']).'">'.e($person['email']).'</a></small>'
            .'<div class="approval-company"><a href="'.e(yw_customer_url($companyId,'mitarbeiter')).'">'.e($person['shop_name']).' · '.e($person['legal_name']).'</a></div>'
            .'<div class="approval-meta"><span class="badge pending">Wartet auf Freigabe</span><span class="muted">Registriert '.($created?e(date('d.m.Y H:i',$created)):'–').'</span></div></div></div>'
            .'<div class="approval-actions"><form method="post" data-confirm="Registrierung von '.e($person['name']).' wirklich ablehnen?">'.csrf_field().'<input type="hidden" name="action" value="employee_access"><input type="hidden" name="account_id" value="'.$id.'"><input type="hidden" name="decision" value="rejected"><input type="hidden" name="return_tab" value="approvals"><input type="hidden" name="return_url" value="'.e($pageUrl($page)).'"><button class="btn small" type="submit">Ablehnen</button></form>'
            .'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="employee_access"><input type="hidden" name="account_id" value="'.$id.'"><input type="hidden" name="decision" value="approved"><input type="hidden" name="return_tab" value="approvals"><input type="hidden" name="return_url" value="'.e($pageUrl($page)).'"><button class="btn small primary" type="submit">Freigeben</button></form></div></article>';
    }
    echo '</div>';
    if($pages>1){
        echo '<nav class="approval-pagination" aria-label="Seiten der Freigabenliste"><p class="muted">Seite '.$page.' von '.$pages.' · '.min($total,$offset+1).'–'.min($total,$offset+count($people)).' von '.$total.'</p><div class="actions">';
        if($page>1)echo '<a class="btn small" href="'.e($pageUrl($page-1)).'">← Zurück</a>';
        if($page<$pages)echo '<a class="btn small" href="'.e($pageUrl($page+1)).'">Weiter →</a>';
        echo '</div></nav>';
    }
    echo '</section>';
}
