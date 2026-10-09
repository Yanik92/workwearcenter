<?php
declare(strict_types=1);

function employee_register_page_by_slug(string $slug): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1');
    $q->execute([$slug]); $company=$q->fetch();
    if(!$company) { http_response_code(404); simple_message('Nicht verfügbar','Firmenshop nicht gefunden.'); return; }
    $error='';
    try { [$pinRequired,$approvalRequired]=yw_registration_policy($company['registration_mode']); }
    catch(DomainException $e) { simple_message('Nicht verfügbar',$e->getMessage()); return; }
    if(is_post()){
        verify_csrf();
        try {
            // Unauffällige Bot-Falle: echte Nutzer sehen dieses Feld nicht.
            if(trim(is_string($_POST['website']??null)?$_POST['website']:'')!==''){
                simple_message('Registrierung geprüft','Wenn die Angaben gültig sind, kann der Zugang nach der vorgesehenen Firmenfreigabe verwendet werden.');
                return;
            }
            $globalKey='register-global-'.$company['id'];
            if(login_throttled($globalKey,40)) throw new DomainException('Zu viele Registrierungen in kurzer Zeit. Bitte später erneut versuchen.');
            record_login_failure($globalKey);
            $name=trim(is_string($_POST['name']??null)?$_POST['name']:'');
            $email=strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if($name==='' || mb_strlen($name)>160) throw new DomainException('Bitte Namen mit höchstens 160 Zeichen eingeben.');
            if(strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('E-Mail-Adresse ungültig.');
            $emailKey='register-email-'.$company['id'].'-'.hash('sha256',$email);
            if(login_throttled($emailKey,8)) throw new DomainException('Zu viele Versuche für diese E-Mail-Adresse. Bitte später erneut versuchen.');
            record_login_failure($emailKey);
            if(strlen($password)<12 || strlen($password)>72) throw new DomainException('Passwort: 12 bis 72 Bytes verwenden.');
            $passwordHash=password_hash($password,PASSWORD_DEFAULT);
            db()->beginTransaction();
            $company=yw_locked_company((int)$company['id']);
            if(!(int)$company['active']) throw new DomainException('Firmenshop deaktiviert.');
            [$pinRequired,$approvalRequired]=yw_registration_policy($company['registration_mode']);
            $pin=is_string($_POST['pin']??null)?$_POST['pin']:'';
            if($pinRequired && !password_verify($pin,(string)$company['registration_pin_hash'])) throw new DomainException('Firmen-PIN ungültig.');
            $q=db()->prepare('SELECT id FROM employee_accounts WHERE company_id=? AND email=? FOR UPDATE');
            $q->execute([$company['id'],$email]);
            if($q->fetchColumn()){
                db()->commit();
                simple_message('Registrierung geprüft','Wenn die Angaben gültig sind, kann der Zugang nach der vorgesehenen Firmenfreigabe verwendet werden.');
                return;
            }
            $status=$approvalRequired?'pending':'approved';
            $profileId=yw_default_equipment_profile_id((int)$company['id']);
            $q=db()->prepare('INSERT INTO employee_accounts(company_id,name,email,password_hash,role,registration_status,active,equipment_profile_id) VALUES(?,?,?,?,?,?,1,?)');
            $q->execute([$company['id'],$name,$email,$passwordHash,'employee',$status,$profileId]);
            $id=(int)db()->lastInsertId();
            db()->commit();
            clear_login_failures($emailKey);
            audit_log('employee.registered','employee',$id,['company_id'=>(int)$company['id'],'status'=>$status]);
            notify_company_managers((int)$company['id'],'Neue Registrierung',"Ein neuer Zugang wurde angelegt.\n".workwearcenter_url($slug.'/mitarbeiter'));
            simple_message('Registrierung geprüft',$approvalRequired?'Wenn die Angaben gültig sind, wird dein Firmenverantwortlicher den Zugang prüfen.':'Wenn die Angaben gültig sind, kannst du den Firmenzugang anschließend verwenden.');
            return;
        } catch(Throwable $e) {
            if(db()->inTransaction()) db()->rollBack();
            if(!$e instanceof DomainException) error_log('Registration: '.$e->getMessage());
            $error=$e instanceof DomainException?$e->getMessage():'Registrierung konnte nicht gespeichert werden.';
        }
    }
    render_head('Registrierung – '.$company['shop_name']);
    echo company_shop_style($company);
    echo '<style>
      body{background:#f4f6f9}.portal-register{min-height:100vh;display:grid;grid-template-columns:minmax(300px,.82fr) 1.18fr}.portal-register-intro{position:relative;overflow:hidden;background:#102b42;color:#fff;padding:clamp(34px,5vw,66px);display:flex;flex-direction:column;justify-content:space-between;border-left:8px solid var(--shop-accent)}.portal-register-logo{width:min(100%,300px);min-height:72px;background:#fff;border-radius:4px;padding:12px 16px;display:flex;align-items:center}.portal-register-logo img{display:block;max-width:100%;max-height:62px;object-fit:contain}.portal-register-logo strong{color:#112a40;font-size:1.4rem;line-height:1.1}.portal-register-intro h1{color:#fff;font-size:clamp(2.35rem,4.5vw,4.5rem);line-height:1;margin:16px 0}.portal-register-intro p{color:rgba(255,255,255,.78);line-height:1.65;max-width:570px}.portal-register-form{display:grid;place-items:center;padding:40px 24px}.portal-register-card{width:min(100%,620px);background:#fff;border:1px solid #dce2e7;border-radius:6px;padding:clamp(26px,4vw,42px);box-shadow:0 16px 40px rgba(15,23,42,.08)}.portal-register-card h2{font-size:2rem;margin:0 0 8px}.portal-register-card>p{color:#667085;margin:0 0 25px}.portal-register-back{margin:22px 0 0;padding-top:20px;border-top:1px solid #e7ebf0}@media(max-width:850px){.portal-register{grid-template-columns:1fr}.portal-register-intro{min-height:auto;gap:38px;padding:28px}.portal-register-form{padding:24px 18px 38px}}
    </style>';
    echo '<main class="portal-register"><section class="portal-register-intro"><div class="portal-register-logo">';
    $companyLogo=yw_company_logo_path($company);
    if($companyLogo!=='') echo '<img src="/'.e($companyLogo).'" alt="'.e($company['legal_name']).'">';
    else echo '<strong>'.e($company['legal_name']).'</strong>';
    echo '</div><div><div class="company-shop-kicker">PERSÖNLICHER ZUGANG</div><h1>'.e($company['shop_name']).'</h1><p>Registriere dich einmalig. Danach findest du hier ausschließlich die für dein Unternehmen freigegebene Firmenbekleidung.</p></div></section><section class="portal-register-form"><div class="portal-register-card"><h2>Zugang anlegen</h2><p>'.($approvalRequired?'Deine Registrierung wird anschließend von einem Firmenverantwortlichen geprüft.':'Dein persönlicher Zugang kann anschließend direkt verwendet werden.').'</p>';
    if($error) echo '<div class="flash error">'.e($error).'</div>';
    echo '<form method="post" class="form-stack">'.csrf_field().'<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>Name<input name="name" maxlength="160" required autocomplete="name"></label><label>E-Mail<input type="email" name="email" maxlength="190" required autocomplete="email"></label><label>Passwort<input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label>';
    if($pinRequired) echo '<label>Firmen-PIN<input type="password" name="pin" required autocomplete="off"></label>';
    echo '<button class="btn primary">Zugang beantragen</button></form><p class="portal-register-back"><a href="/'.e($slug).'/login">Zurück zum Login</a></p></div></section></main>';
    company_shop_footer($company);
    render_foot();
}

function yw_registration_decision(array $company,array $actor): never {
    verify_csrf();
    $target=null;$decision='';$changed=0;
    try {
        db()->beginTransaction();
        yw_locked_company((int)$company['id']);
        $q=db()->prepare("SELECT id FROM employee_accounts WHERE id=? AND company_id=? AND role='manager' AND active=1 AND registration_status='approved' FOR UPDATE");
        $q->execute([$actor['id'],$company['id']]);
        if(!$q->fetchColumn()) throw new DomainException('Keine Berechtigung.');
        $id=(int)($_POST['account_id']??0); $decision=(string)($_POST['decision']??'');
        if(!in_array($decision,['approved','rejected'],true)) throw new DomainException('Entscheidung ungültig.');
        $q=db()->prepare("SELECT id,name,email FROM employee_accounts WHERE id=? AND company_id=? AND registration_status='pending' AND role='employee' FOR UPDATE");
        $q->execute([$id,$company['id']]);$target=$q->fetch();
        if($target){$q=db()->prepare("UPDATE employee_accounts SET registration_status=?,active=? WHERE id=? AND company_id=?");$q->execute([$decision,$decision==='approved'?1:0,$id,$company['id']]);$changed=$q->rowCount();}
        db()->commit();
        if($changed){
            audit_log('employee.registration_decided','employee',$id,['company_id'=>(int)$company['id'],'decision'=>$decision]);
            $mailText=$decision==='approved'
                ? "Hallo ".$target['name'].",\n\ndein Zugang zum Firmen-Shop wurde freigegeben.\n\nAnmeldung: ".workwearcenter_url((string)$company['slug'].'/login')
                : "Hallo ".$target['name'].",\n\ndeine Registrierung für den Firmen-Shop wurde nicht freigegeben. Bitte wende dich bei Rückfragen an deinen Firmenverantwortlichen.";
            send_system_mail((string)$target['email'],$decision==='approved'?'Dein Zugang wurde freigegeben':'Information zu deiner Registrierung',$mailText);
        }
        flash('success',$changed?'Registrierung bearbeitet.':'Registrierung war bereits bearbeitet.');
    } catch(Throwable $e){
        if(db()->inTransaction()) db()->rollBack();
        flash('error','Registrierung konnte nicht bearbeitet werden.');
    }
    redirect('/'.$company['slug'].'/mitarbeiter');
}

function company_portal_people(array $company,array $account): void {
    if(!account_is_manager($account)) { http_response_code(403); return; }
    yw_customer_people_page($company,$account);
}
