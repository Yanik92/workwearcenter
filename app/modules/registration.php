<?php
declare(strict_types=1);

function employee_register_page_by_slug(string $slug): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1');
    $q->execute([$slug]); $company=$q->fetch();
    if(!$company) { http_response_code(404); simple_message('Nicht verfügbar','Firmenshop nicht gefunden.'); return; }
    $error='';
    yw_shop_lang_init($company); // 10.12: Sprache vor der Anmeldung (Cookie bzw. Browser)
    try { [$pinRequired,$approvalRequired]=yw_registration_policy($company['registration_mode']); }
    catch(DomainException $e) { simple_message(t('err.unavailable_title'),$e->getMessage()); return; }
    $intro=[t('reg.kicker'),(string)$company['shop_name'],t('reg.intro')];
    if(is_post()){
        verify_csrf();
        try {
            // Unauffällige Bot-Falle: echte Nutzer sehen dieses Feld nicht.
            if(trim(is_string($_POST['website']??null)?$_POST['website']:'')!==''){
                yw_shop_auth_message($company,$slug,t('reg.checked_title'),t('reg.checked_generic'),$intro);
                return;
            }
            $globalKey='register-global-'.$company['id'];
            if(login_throttled($globalKey,40)) throw new DomainException(t('reg.too_many'));
            record_login_failure($globalKey);
            $name=trim(is_string($_POST['name']??null)?$_POST['name']:'');
            $email=strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if($name==='' || mb_strlen($name)>160) throw new DomainException(t('flash.name_invalid'));
            if(strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException(t('reg.email_invalid'));
            $emailKey='register-email-'.$company['id'].'-'.hash('sha256',$email);
            if(login_throttled($emailKey,8)) throw new DomainException(t('reg.too_many_email'));
            record_login_failure($emailKey);
            if(strlen($password)<12 || strlen($password)>72) throw new DomainException(t('pw.length'));
            $passwordHash=password_hash($password,PASSWORD_DEFAULT);
            db()->beginTransaction();
            $company=yw_locked_company((int)$company['id']);
            if(!(int)$company['active']) throw new DomainException(t('err.shop_unavailable'));
            [$pinRequired,$approvalRequired]=yw_registration_policy($company['registration_mode']);
            $pin=is_string($_POST['pin']??null)?$_POST['pin']:'';
            // Firmen-PIN zusätzlich IP-unabhängig je Firma drosseln (30 Fehlversuche / 15 Minuten), sonst lässt sie sich über viele Adressen erraten.
            $pinKey='register-pin-'.(int)$company['id'];
            if($pinRequired && login_throttled($pinKey,30,false)) throw new DomainException(t('reg.too_many'));
            if($pinRequired && !password_verify($pin,(string)$company['registration_pin_hash'])){db()->rollBack();record_login_failure($pinKey,false);throw new DomainException(t('reg.pin_invalid'));}
            $q=db()->prepare('SELECT id FROM employee_accounts WHERE company_id=? AND email=? FOR UPDATE');
            $q->execute([$company['id'],$email]);
            if($q->fetchColumn()){
                db()->commit();
                yw_shop_auth_message($company,$slug,t('reg.checked_title'),t('reg.checked_generic'),$intro);
                return;
            }
            $status=$approvalRequired?'pending':'approved';
            $profileId=yw_default_equipment_profile_id((int)$company['id']);
            // 10.12: Die bei der Registrierung angezeigte Sprache gilt auch für Shop und Mails.
            $q=db()->prepare('INSERT INTO employee_accounts(company_id,name,email,password_hash,role,registration_status,active,equipment_profile_id,language) VALUES(?,?,?,?,?,?,1,?,?)');
            $q->execute([$company['id'],$name,$email,$passwordHash,'employee',$status,$profileId,yw_lang()]);
            $id=(int)db()->lastInsertId();
            db()->commit();
            clear_login_failures($emailKey);
            audit_log('employee.registered','employee',$id,['company_id'=>(int)$company['id'],'status'=>$status]);
            $peopleUrl=workwearcenter_url($slug.'/mitarbeiter');
            notify_company_managers((int)$company['id'],fn(array $m)=>[t('mail.registration.subject'),t('mail.registration.body')."\n".$peopleUrl]);
            yw_shop_auth_message($company,$slug,t('reg.checked_title'),$approvalRequired?t('reg.checked_approval'):t('reg.checked_direct'),$intro);
            return;
        } catch(Throwable $e) {
            if(db()->inTransaction()) db()->rollBack();
            if(!$e instanceof DomainException) error_log('Registration: '.$e->getMessage());
            $error=$e instanceof DomainException?$e->getMessage():t('reg.failed');
        }
    }
    $card='<h2>'.e(t('reg.title')).'</h2><p>'.e($approvalRequired?t('reg.sub_approval'):t('reg.sub_direct')).'</p>';
    if($error) $card.='<div class="flash error" role="alert">'.e($error).'</div>';
    $card.='<form method="post" class="form-stack">'.csrf_field().'<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>'.e(t('common.name')).'<input name="name" maxlength="160" required autocomplete="name"></label><label>'.e(t('common.email')).'<input type="email" name="email" maxlength="190" required autocomplete="email"></label><label>'.e(t('common.password')).' <small>'.e(t('pw.min12')).'</small><input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>';
    if($pinRequired) $card.='<label>'.e(t('reg.pin')).' <small>'.e(t('reg.pin_hint')).'</small><input type="password" name="pin" required autocomplete="off"></label>';
    $card.='<button class="cp-btn-primary" type="submit">'.e(t('reg.submit')).'</button></form><div class="cp-login-help"><span>'.e(t('reg.already')).' <a href="/'.e($slug).'/login">'.e(t('auth.to_login')).'</a></span></div>';
    yw_shop_auth_page($company,t('reg.page_title',['shop'=>$company['shop_name']]),$intro,$card);
}

function yw_registration_decision(array $company,array $actor,string $back='/mitarbeiter'): never {
    verify_csrf();
    $target=null;$decision='';$changed=0;
    try {
        db()->beginTransaction();
        yw_locked_company((int)$company['id']);
        $q=db()->prepare("SELECT id FROM employee_accounts WHERE id=? AND company_id=? AND role='manager' AND active=1 AND registration_status='approved' FOR UPDATE");
        $q->execute([$actor['id'],$company['id']]);
        if(!$q->fetchColumn()) throw new DomainException(t('err.no_permission'));
        $id=(int)($_POST['account_id']??0); $decision=(string)($_POST['decision']??'');
        if(!in_array($decision,['approved','rejected'],true)) throw new DomainException(t('err.decision_invalid'));
        $q=db()->prepare("SELECT id,name,email,language FROM employee_accounts WHERE id=? AND company_id=? AND registration_status='pending' AND role='employee' FOR UPDATE");
        $q->execute([$id,$company['id']]);$target=$q->fetch();
        if($target){$q=db()->prepare("UPDATE employee_accounts SET registration_status=?,active=? WHERE id=? AND company_id=?");$q->execute([$decision,$decision==='approved'?1:0,$id,$company['id']]);$changed=$q->rowCount();}
        db()->commit();
        if($changed){
            audit_log('employee.registration_decided','employee',$id,['company_id'=>(int)$company['id'],'decision'=>$decision]);
            yw_mail_to_person($target+['shop_languages'=>$company['shop_languages']??null],yw_access_mail($decision==='approved',$company,true));
        }
        flash('success',$changed?t('people.registration_done'):t('people.registration_already'));
    } catch(Throwable $e){
        if(db()->inTransaction()) db()->rollBack();
        flash('error',t('people.registration_failed'));
    }
    // 10.15: in der Personenliste mit derselben Suche und Seite zurück
    redirect($back==='/mitarbeiter'?yw_people_return_url((string)$company['slug'],'personen'):'/'.$company['slug']);
}

function company_portal_people(array $company,array $account): void {
    if(!account_is_manager($account)) { http_response_code(403); return; }
    yw_customer_people_page($company,$account);
}

/**
 * 10.12: Mail „Zugang freigegeben“ bzw. „nicht freigegeben“ als Closure für yw_mail_to_person()
 * (Text in der Sprache der Person). $registration: Antwort auf eine Registrierung.
 */
function yw_access_mail(bool $approved,array $company,bool $registration=false): Closure {
    $loginUrl=workwearcenter_url((string)$company['slug'].'/login');
    return function(array $p) use($approved,$registration,$loginUrl): array {
        if($approved)return [t('mail.access.approved_subject'),t('mail.hello',['name'=>$p['name']])."\n\n".t('mail.access.approved_body')."\n\n".t('mail.access.login').': '.$loginUrl];
        return $registration
            ? [t('mail.access.reg_rejected_subject'),t('mail.hello',['name'=>$p['name']])."\n\n".t('mail.access.reg_rejected_body')]
            : [t('mail.access.rejected_subject'),t('mail.hello',['name'=>$p['name']])."\n\n".t('mail.access.rejected_body')];
    };
}
