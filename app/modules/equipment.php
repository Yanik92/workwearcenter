<?php
declare(strict_types=1);

function yw_equipment_profiles(int $companyId,bool $activeOnly=false): array {
    $sql='SELECT ep.*,COUNT(epp.product_id) product_count FROM yw_equipment_profiles ep LEFT JOIN yw_equipment_profile_products epp ON epp.profile_id=ep.id WHERE ep.company_id=?';
    if($activeOnly) $sql.=' AND ep.active=1';
    $sql.=' GROUP BY ep.id ORDER BY ep.is_default DESC,ep.active DESC,ep.name';
    $q=db()->prepare($sql);$q->execute([$companyId]);return $q->fetchAll();
}

function yw_equipment_profile_options(int $companyId): array {
    $profiles=[];
    foreach(yw_equipment_profiles($companyId,true) as $profile)$profiles[(int)$profile['id']]=$profile;
    return $profiles;
}

function yw_equipment_profile_id(int $companyId,mixed $raw,bool $allowEmpty=true): ?int {
    $id=is_scalar($raw)?(int)$raw:0;
    if($id===0 && $allowEmpty)return null;
    $q=db()->prepare('SELECT id FROM yw_equipment_profiles WHERE id=? AND company_id=? AND active=1');$q->execute([$id,$companyId]);
    if(!$q->fetchColumn())throw new DomainException('Ausstattungsprofil ungültig oder nicht aktiv.');
    return $id;
}

function yw_default_equipment_profile_id(int $companyId): ?int {
    $q=db()->prepare('SELECT id FROM yw_equipment_profiles WHERE company_id=? AND active=1 AND is_default=1 ORDER BY id LIMIT 1');$q->execute([$companyId]);
    $id=$q->fetchColumn();return $id===false?null:(int)$id;
}

function yw_account_equipment_profile(array $account): ?array {
    $id=(int)($account['equipment_profile_id']??0);if(!$id)return null;
    $q=db()->prepare('SELECT * FROM yw_equipment_profiles WHERE id=? AND company_id=? AND active=1');$q->execute([$id,(int)$account['company_id']]);
    return $q->fetch()?:null;
}

/** Null means unrestricted legacy collection; an array means the assigned profile is binding. */
function yw_account_profile_rules(array $account): ?array {
    $assignedId=(int)($account['equipment_profile_id']??0);if(!$assignedId)return null;
    $profile=yw_account_equipment_profile($account);if(!$profile)return [];
    $q=db()->prepare('SELECT product_id,starter_qty,yearly_target_qty FROM yw_equipment_profile_products WHERE profile_id=?');$q->execute([$profile['id']]);
    $rules=[];foreach($q as $row)$rules[(int)$row['product_id']]=['starter_qty'=>(int)$row['starter_qty'],'yearly_target_qty'=>$row['yearly_target_qty']===null?null:(int)$row['yearly_target_qty']];
    return $rules;
}

function yw_effective_product_limit(array $product,?array $profileRule): ?int {
    if($profileRule!==null && $profileRule['yearly_target_qty']!==null)return (int)$profileRule['yearly_target_qty'];
    return $product['yearly_limit_qty']===null?null:(int)$product['yearly_limit_qty'];
}

function yw_equipment_profile_select(array $profiles,?int $selected,string $name='equipment_profile_id'): string {
    $html='<label>Ausstattungsprofil<select name="'.e($name).'"><option value="">Kein Profil · gesamtes Sortiment</option>';
    foreach($profiles as $id=>$profile)$html.='<option value="'.$id.'" '.($selected===$id?'selected':'').'>'.e($profile['name']).($profile['is_default']?' · Standard':'').'</option>';
    return $html.'</select></label>';
}
