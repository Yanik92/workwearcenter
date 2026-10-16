<?php
declare(strict_types=1);
require_once __DIR__.'/site.php';

function delete_site_image(string $path): void {
    if(!yw_upload_path_ok($path,'branding')) return;
    $file=dirname(__DIR__, 2).'/'.$path;
    if(is_file($file)) @unlink($file);
}


function sanitize_rich_text(string $html): string {
    $html=trim($html);
    if($html==='') return '';

    // Nur bewusst freigegebene HTML-Tags behalten.
    $html=strip_tags($html,'<p><div><br><strong><b><em><i><u><span>');

    // Eventhandler und riskante Attribute vollständig entfernen.
    $html=preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i','',$html);
    $html=preg_replace('/\s(?:href|src|class|id|data-[a-z0-9_-]+)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i','',$html);

    // Attribute aller Tags außer style bei <span> entfernen.
    $html=preg_replace_callback('/<([a-z]+)([^>]*)>/i',function($m){
        $tag=strtolower($m[1]);
        $attrs=$m[2]??'';
        if($tag!=='span') return '<'.$tag.'>';

        $style='';
        if(preg_match('/style\s*=\s*"([^"]*)"/i',$attrs,$sm) || preg_match("/style\s*=\s*'([^']*)'/i",$attrs,$sm)){
            $raw=$sm[1];
            $allowed=[];
            foreach(explode(';',$raw) as $rule){
                if(!str_contains($rule,':')) continue;
                [$prop,$val]=array_map('trim',explode(':',$rule,2));
                $prop=strtolower($prop);
                if($prop==='font-family'){
                    $safe=preg_replace('/[^a-zA-Z0-9 ,\-"\']/', '', $val);
                    if($safe!=='') $allowed[]='font-family:'.$safe;
                } elseif($prop==='font-size'){
                    if(preg_match('/^(12|14|16|18|20|22|24|28|32|36|40|48)px$/',$val)) $allowed[]='font-size:'.$val;
                } elseif($prop==='font-weight'){
                    if(in_array(strtolower($val),['bold','600','700','800','900'],true)) $allowed[]='font-weight:'.$val;
                } elseif($prop==='font-style'){
                    if(strtolower($val)==='italic') $allowed[]='font-style:italic';
                } elseif($prop==='text-decoration' || $prop==='text-decoration-line'){
                    if(str_contains(strtolower($val),'underline')) $allowed[]='text-decoration:underline';
                }
            }
            $style=implode(';',$allowed);
        }
        return $style!==''?'<span style="'.htmlspecialchars($style,ENT_QUOTES,'UTF-8').'">':'<span>';
    },$html);

    return $html;
}

function rich_text(string $html): string {
    return sanitize_rich_text($html);
}

function yw_public_link(mixed $value,string $fallback=''): string {
    $value=trim(is_string($value)?$value:'');
    if($value!=='' && str_starts_with($value,'/') && !str_starts_with($value,'//') && !preg_match('/[\x00-\x1F\x7F]/',$value)) return $value;
    if($value!=='' && filter_var($value,FILTER_VALIDATE_URL) && strtolower((string)parse_url($value,PHP_URL_SCHEME))==='https') return $value;
    return $fallback;
}

function yw_site_image_url(string $key,string $fallback): string {
    $value=trim(current_site_setting($key,''));
    // 10.19.1: Fehlt die hinterlegte Datei auf dem Server (z. B. nicht per FTP übertragen), Standardbild statt kaputtem Bild.
    $exists=static fn(string $rel): bool => is_file(dirname(__DIR__,2).'/'.$rel);
    if($value!=='' && yw_upload_path_ok($value,'branding') && $exists($value)) return '/'.$value;
    if($value!=='' && preg_match('#^assets/media/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:png|jpe?g|webp)$#D',$value) && $exists($value)) return '/'.$value;
    return '/'.ltrim($fallback,'/');
}

function rich_editor_field(string $name,string $label,string $value,int $minHeight=150): string {
    $safeName=e($name);
    $safeLabel=e($label);
    $safeValue=rich_text($value);

    return '<div class="rich-field">'
        .'<label>'.$safeLabel.'</label>'
        .'<div class="rich-toolbar" data-for="'.$safeName.'">'
            .'<button type="button" data-cmd="bold" title="Fett" aria-label="Fett"><strong>B</strong></button>'
            .'<button type="button" data-cmd="italic" title="Kursiv" aria-label="Kursiv"><em>I</em></button>'
            .'<button type="button" data-cmd="underline" title="Unterstrichen" aria-label="Unterstrichen"><u>U</u></button>'
        .'</div>'
        .'<div class="rich-editor" contenteditable="true" data-name="'.$safeName.'" style="min-height:'.$minHeight.'px">'.$safeValue.'</div>'
        .'<textarea class="rich-hidden" name="'.$safeName.'" hidden>'.e($value).'</textarea>'
        .'</div>';
}


function rich_editor_script(): string {
    // Logik liegt in assets/app.js (CSP ohne 'unsafe-inline').
    return '';
}
function upload_site_image(?array $file,string $old='',string $prefix='site'): string {
    if(!$file) return $old;
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($error===UPLOAD_ERR_NO_FILE) return $old;
    if($error!==UPLOAD_ERR_OK) throw new RuntimeException(upload_error_message($error,'Bild'));

    $tmp=(string)($file['tmp_name']??'');
    $size=(int)($file['size']??0);
    if(!$tmp || !is_uploaded_file($tmp)) throw new RuntimeException('Die hochgeladene Bilddatei wurde vom Server nicht als gültiger Upload erkannt.');
    if($size<=0) throw new RuntimeException('Die Bilddatei ist leer.');
    if($size>10*1024*1024) throw new RuntimeException('Bild darf maximal 10 MB groß sein.');

    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=(string)$finfo->file($tmp);
    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
    if(!$ext) throw new RuntimeException('Nicht unterstütztes Bildformat ('.$mime.'). Erlaubt sind PNG, JPG oder WEBP.');
    yw_validate_uploaded_raster($tmp,'Website-Bild');

    $dir=dirname(__DIR__, 2).'/uploads/branding';
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) {
        throw new RuntimeException('Der Ordner uploads/branding konnte nicht erstellt werden.');
    }
    if(!is_writable($dir)) throw new RuntimeException('Der Ordner uploads/branding ist nicht beschreibbar. Bitte Schreibrechte prüfen.');

    $safePrefix=preg_replace('/[^a-z0-9_-]/i','-', $prefix) ?: 'site';
    $name=$safePrefix.'-'.bin2hex(random_bytes(8)).'.'.$ext;
    if(!yw_move_upload($tmp,$dir.'/'.$name)) throw new RuntimeException('Bild konnte nicht nach uploads/branding gespeichert werden.');

    return 'uploads/branding/'.$name;
}

function upload_site_logo(array $file,string $old=''): string {
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($error===UPLOAD_ERR_NO_FILE) return $old;
    if($error!==UPLOAD_ERR_OK) throw new RuntimeException(upload_error_message($error,'Logo'));

    $tmp=(string)($file['tmp_name']??'');
    $size=(int)($file['size']??0);
    if(!$tmp || !is_uploaded_file($tmp)) throw new RuntimeException('Die hochgeladene Logo-Datei wurde vom Server nicht als gültiger Upload erkannt.');
    if($size<=0) throw new RuntimeException('Die Logo-Datei ist leer.');
    if($size>5*1024*1024) throw new RuntimeException('Logo darf maximal 5 MB groß sein.');

    $finfo=new finfo(FILEINFO_MIME_TYPE);
    $mime=(string)$finfo->file($tmp);
    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
    if(!$ext) throw new RuntimeException('Nicht unterstütztes Logoformat ('.$mime.'). Erlaubt sind PNG, JPG oder WEBP.');
    yw_validate_uploaded_raster($tmp,'Website-Logo');

    $dir=dirname(__DIR__, 2).'/uploads/branding';
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) {
        throw new RuntimeException('Der Ordner uploads/branding konnte nicht erstellt werden.');
    }
    if(!is_writable($dir)) throw new RuntimeException('Der Ordner uploads/branding ist nicht beschreibbar. Bitte Schreibrechte prüfen.');

    $name='main-logo-'.bin2hex(random_bytes(6)).'.'.$ext;
    if(!yw_move_upload($tmp,$dir.'/'.$name)) throw new RuntimeException('Logo konnte nicht nach uploads/branding gespeichert werden.');

    return 'uploads/branding/'.$name;
}

function site_brand_style(): string {
    $primary=strtolower(trim(current_site_setting('site_primary','#fc6904')));
    $accent=strtolower(trim(current_site_setting('site_accent','#fc6904')));
    if(in_array($primary,['#287f9d','#b64f1d'],true))$primary='#fc6904';
    if(in_array($accent,['#c54b55','#c45c27'],true))$accent='#fc6904';
    if(!color_ok($primary))$primary='#fc6904';
    if(!color_ok($accent))$accent='#fc6904';
    return '<style>
    :root{
      --primary:'.e($primary).';
      --accent:'.e($accent).';
      --bg:#ffffff;
      --surface:#f3f0ea;
      --text:#292d2f;
      --muted:#777973;
      --border:#e5dfd6;
      --radius:10px;
      --max:1240px;
      --shadow:0 18px 45px rgba(48,40,34,.10);
    }

    body{background:var(--bg);color:var(--text)}
    .wrap{max-width:var(--max);margin:0 auto;padding-left:24px;padding-right:24px}

    /* Header */
    .public-header{
      position:sticky;
      top:0;
      z-index:50;
      background:#292d2f;
      border-bottom:1px solid rgba(255,255,255,.10);
      box-shadow:0 6px 24px rgba(0,0,0,.10);
    }
    .public-nav{
      min-height:82px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:24px;
    }
    .main-site-logo{
      max-height:58px;
      max-width:250px;
      object-fit:contain;
      display:block;
    }
    .site-logo-fallback{
      display:grid;
      place-items:center;
      width:54px;
      height:54px;
      border-radius:14px;
      background:#fff;
      color:var(--primary);
      font-weight:900;
      text-decoration:none;
    }
    .public-links{display:flex;gap:24px;align-items:center}
    .public-links a{
      text-decoration:none;
      color:#fff;
      font-weight:700;
      opacity:.95;
    }
    .public-links a:hover{color:var(--accent);opacity:1}
    .mobile-menu{display:none;position:relative;color:#fff}
    .mobile-menu summary{list-style:none;cursor:pointer;border:1px solid rgba(255,255,255,.26);border-radius:8px;padding:9px 12px;font-weight:800}
    .mobile-menu summary::-webkit-details-marker{display:none}
    .mobile-menu nav{position:absolute;right:0;top:48px;width:min(300px,calc(100vw - 36px));display:grid;background:#fff;border:1px solid var(--border);border-radius:12px;padding:8px;box-shadow:var(--shadow)}
    .mobile-menu a{padding:11px 12px;border-radius:7px;color:var(--text);text-decoration:none;font-weight:750}
    .mobile-menu a:hover{background:#fff4eb;color:#a94604}
    .login-brandbar{background:var(--primary);border-bottom:1px solid rgba(255,255,255,.1)}.login-brandbar .wrap{min-height:82px;display:flex;align-items:center}.login-shell{min-height:calc(100vh - 176px)!important}

    /* Startseite Hero */
    .wc-hero{
      position:relative;
      min-height:620px;
      display:flex;
      align-items:center;
      overflow:hidden;
      background:var(--primary);
      color:#fff;
    }
    .wc-hero-bg{position:absolute;inset:0;background-size:cover;background-position:center}
    .wc-hero-bg:after{
      content:"";
      position:absolute;
      inset:0;
      background:linear-gradient(90deg,rgba(8,20,35,.86),rgba(8,20,35,.34));
    }
    .wc-hero-inner{position:relative;z-index:2;max-width:820px;padding:96px 24px}
    .wc-hero h1{font-size:clamp(2.8rem,6vw,5.5rem);line-height:1;margin:.35rem 0 1.25rem}
    .wc-hero p{font-size:1.18rem;line-height:1.7;max-width:700px}
    .btn.primary{background:var(--accent);border-color:var(--accent);color:#fff}

    /* Allgemeine Sektionen */
    .wc-section{padding:78px 0}
    .wc-section.alt{background:var(--surface)}
    .wc-intro{max-width:860px}
    .wc-intro h2{font-size:clamp(2.1rem,4vw,3.8rem);line-height:1.08}

    .wc-feature-grid,.wc-process-grid{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:24px;
    }
    .wc-feature,.wc-process-item{
      border:1px solid var(--border);
      border-radius:var(--radius);
      background:#fff;
      overflow:hidden;
      box-shadow:0 10px 30px rgba(16,24,40,.06);
    }
    .wc-feature img{width:100%;height:250px;object-fit:cover;display:block}
    .wc-feature-body{padding:28px}
    .wc-process-item{padding:28px}

    /* Unterseiten */
    .page-hero{
      padding:88px 0 72px;
      background:var(--primary);
      color:#fff;
    }
    .page-hero h1{
      font-size:clamp(2.5rem,5vw,4.5rem);
      line-height:1.05;
      margin-bottom:20px;
      color:#fff;
    }
    .page-hero p{
      max-width:780px;
      font-size:1.12rem;
      line-height:1.7;
      color:rgba(255,255,255,.88);
      margin:0;
    }

    .subpage-content-section{
      padding:72px 0 90px;
      background:var(--surface);
    }
    .subpage-content-card{
      background:#fff;
      border:1px solid var(--border);
      border-radius:var(--radius);
      box-shadow:var(--shadow);
      padding:48px;
    }
    .subpage-content-card.with-image{
      display:grid;
      grid-template-columns:1.05fr .95fr;
      gap:48px;
      align-items:center;
    }
    .subpage-copy{
      font-size:1.05rem;
      line-height:1.85;
      color:#344054;
    }
    .subpage-copy p{margin:0}.subpage-copy p+p{margin-top:1rem}
    .subpage-copy h2,.subpage-copy h3{color:var(--primary);line-height:1.2;margin:1.8rem 0 .65rem}
    .subpage-copy ul{padding-left:1.2rem}.subpage-copy li+li{margin-top:.45rem}
    .subpage-image img{
      width:100%;
      max-height:500px;
      object-fit:cover;
      border-radius:14px;
      display:block;
    }
    .marketing-section{padding:78px 0;background:#fff}
    .marketing-section.alt{background:var(--surface)}
    .marketing-split{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(360px,.95fr);gap:68px;align-items:center}
    .marketing-split.reverse{grid-template-columns:minmax(360px,.95fr) minmax(0,1.05fr)}
    .marketing-photo{position:relative;min-height:510px;overflow:hidden;border-radius:3px;background:var(--primary)}
    .marketing-photo img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
    .marketing-photo:after{content:"Yanik Textildruck · echte Kundenarbeit";position:absolute;left:0;bottom:0;background:var(--primary);color:#fff;padding:12px 15px;font-size:.68rem;font-weight:850;letter-spacing:.1em;text-transform:uppercase}
    .marketing-kicker{display:flex;align-items:center;gap:10px;color:var(--accent);font-size:.72rem;font-weight:900;letter-spacing:.15em;text-transform:uppercase}.marketing-kicker:before{content:"";width:30px;height:3px;background:var(--accent)}
    .marketing-copy h2{font-size:clamp(2rem,4vw,3.8rem);line-height:1.03;letter-spacing:-.04em;margin:18px 0 22px;color:var(--primary)}
    .marketing-copy>p{font-size:1.03rem;line-height:1.75;color:#526171}
    .benefit-list{display:grid;gap:0;margin-top:30px;border-top:3px solid var(--primary)}
    .benefit-item{display:grid;grid-template-columns:42px 1fr;gap:13px;padding:20px 0;border-bottom:1px solid var(--border)}
    .benefit-item strong:first-child{color:var(--accent);font-size:.75rem}.benefit-item h3{font-size:1.05rem;margin:0 0 5px}.benefit-item p{margin:0;color:#657383;font-size:.9rem;line-height:1.55}
    .service-grid{display:grid;grid-template-columns:repeat(3,1fr);border-top:1px solid var(--border);border-left:1px solid var(--border);margin-top:38px}
    .service-card{padding:28px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);background:#fff;min-height:210px}.service-card svg{width:26px;height:26px;color:var(--accent);margin-bottom:22px}.service-card h3{font-size:1.15rem;margin:0 0 9px}.service-card p{font-size:.91rem;line-height:1.62;color:#627080;margin:0}
    .process-list{display:grid;grid-template-columns:repeat(4,1fr);border-top:4px solid var(--primary);margin-top:42px}.process-card{padding:26px 25px 10px 0;border-right:1px solid var(--border)}.process-card+.process-card{padding-left:25px}.process-card:last-child{border-right:0}.process-card>strong{display:block;color:var(--accent);font-size:.72rem;letter-spacing:.13em;margin-bottom:22px}.process-card h3{font-size:1.05rem;margin:0 0 9px}.process-card p{font-size:.88rem;line-height:1.6;color:#657383;margin:0}
    .contact-layout{display:grid;grid-template-columns:.78fr 1.22fr;gap:22px}.contact-panel{padding:38px;background:var(--primary);color:#fff;border-radius:16px}.contact-panel h2{color:#fff;font-size:2rem;margin:0 0 15px}.contact-panel p{color:rgba(255,255,255,.74);line-height:1.7}.contact-lines{display:grid;gap:12px;margin-top:28px}.contact-lines a{color:#fff;font-weight:800}.contact-action{padding:38px;border:1px solid var(--border);background:#fff;border-radius:16px}.contact-action h2{font-size:2rem;margin:0 0 12px}.contact-action p{color:#657383;line-height:1.7}.contact-action .btn{margin-top:18px;padding:14px 18px}
    .marketing-cta{padding:65px 0;background:var(--primary);color:#fff}.marketing-cta-grid{display:flex;justify-content:space-between;align-items:center;gap:34px}.marketing-cta h2{font-size:clamp(2rem,4vw,3.4rem);line-height:1.05;letter-spacing:-.04em;color:#fff;margin:0 0 12px}.marketing-cta p{color:rgba(255,255,255,.72);margin:0}.marketing-cta .btn{flex:none;padding:15px 20px}
    .demo-app{display:grid;background:#fff;border:1px solid var(--border);overflow:hidden;box-shadow:var(--shadow)}.demo-side{background:var(--primary);color:#fff;padding:23px 16px;display:flex;flex-direction:column}.demo-company{display:flex;gap:9px;align-items:center;padding-bottom:18px;border-bottom:1px solid rgba(255,255,255,.13)}.demo-company-mark{width:36px;height:36px;background:var(--accent);display:grid;place-items:center;font-weight:900}.demo-company strong,.demo-company small{display:block}.demo-company small{font-size:.66rem;color:rgba(255,255,255,.56)}.demo-nav{display:grid;gap:6px;margin-top:18px}.demo-nav button{border:0;background:transparent;text-align:left;color:rgba(255,255,255,.6);padding:10px;border-radius:7px;font-weight:750}.demo-nav button.active{background:rgba(255,255,255,.1);color:#fff}.demo-main{background:#f6f8fa}.demo-top{height:70px;padding:0 20px;background:#fff;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}.demo-top-title strong,.demo-top-title small{display:block}.demo-top-title small{color:#7a8693;font-size:.68rem}.demo-avatar{width:34px;height:34px;border-radius:50%;background:#ffe5d2;color:#a64605;display:grid;place-items:center;font-weight:900}.demo-content{padding:22px}.demo-budget{background:var(--primary);color:#fff;border-radius:9px;padding:14px}.demo-budget-row{display:flex;justify-content:space-between;gap:10px;font-size:.72rem}.demo-progress{height:6px;background:rgba(255,255,255,.2);border-radius:8px;margin-top:9px;overflow:hidden}.demo-progress span{display:block;width:73%;height:100%;background:var(--accent)}
    .legal-page{max-width:920px;margin:0 auto;padding:clamp(26px,5vw,52px)}
    .legal-page h1{font-size:clamp(2.1rem,4vw,3.3rem);letter-spacing:-.04em}
    .legal-page .subpage-copy{font-size:.98rem;line-height:1.75}
    .legal-page .subpage-copy>strong{display:block;font-size:1.12rem;color:var(--primary);margin:1.7rem 0 .35rem}
    .legal-page .subpage-copy>div{margin:.35rem 0}

    .content-split{
      display:grid;
      grid-template-columns:1.05fr .95fr;
      gap:48px;
      align-items:center;
    }
    .content-split img{
      width:100%;
      max-height:500px;
      object-fit:cover;
      border-radius:var(--radius);
    }

    .cta-band{
      margin-top:36px;
      padding:44px;
      border-radius:var(--radius);
      background:var(--primary);
      color:#fff;
    }

    .global-footer{
      padding:34px 0;
      border-top:1px solid rgba(255,255,255,.10);
      background:var(--primary);
      color:rgba(255,255,255,.88);
    }
    .global-footer .wrap{
      display:flex;
      gap:18px;
      justify-content:space-between;
      flex-wrap:wrap;
    }
    .global-footer a{color:#fff;text-decoration:none}
    .global-footer a:hover{color:var(--accent)}

    .register-shell{max-width:720px;margin:48px auto}
    .editor-image-preview{display:flex;align-items:center;gap:16px;margin-top:8px;flex-wrap:wrap}
    .editor-image-preview img{max-width:320px;max-height:170px;object-fit:cover;border-radius:12px;border:1px solid var(--border)}
    .editor-block{padding:22px;border:1px solid var(--border);border-radius:14px;background:#fff;margin-bottom:18px}
    .rich-field{margin:14px 0 18px}
    .rich-field>label{display:block;font-weight:700;margin-bottom:8px}
    .rich-toolbar{display:flex;gap:7px;align-items:center;flex-wrap:wrap;padding:9px;background:#f6f7f9;border:1px solid var(--border);border-bottom:0;border-radius:10px 10px 0 0}
    .rich-toolbar select,.rich-toolbar button{height:36px;border:1px solid #d8dde5;background:#fff;border-radius:7px;padding:0 10px;color:#172033}
    .rich-toolbar button{min-width:38px;cursor:pointer;font-size:15px}
    .rich-toolbar button:hover{border-color:var(--accent)}
    .rich-editor{border:1px solid var(--border);border-radius:0 0 10px 10px;padding:14px 16px;background:#fff;line-height:1.65;outline:none;overflow:auto}
    .rich-editor:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(244,123,32,.12)}
    .rich-editor p{margin:.45em 0}
    .product-admin-list{display:flex;flex-direction:column;gap:16px}
    .product-admin-card{display:grid;grid-template-columns:130px minmax(0,1fr) auto;gap:20px;padding:18px 20px;border:1px solid var(--border);border-radius:16px;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.05);align-items:start}
    .product-admin-thumb{width:130px;height:130px;border-radius:12px;background:#f6f7f9;display:grid;place-items:center;overflow:hidden;border:1px solid var(--border)}
    .product-admin-thumb img{width:100%;height:100%;object-fit:contain}
    .product-admin-thumb span{font-size:42px}
    .product-admin-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}
    .product-admin-head h3{margin:0 0 6px}
    .product-admin-meta{display:flex;gap:8px;flex-wrap:wrap;margin:10px 0}
    .product-pill{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;background:#f2f4f7;font-size:.85rem}
    .product-decoration{margin-top:10px;padding:12px 14px;border-radius:10px;background:#fff7ed;border:1px solid #fed7aa}
    .category-manager{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end}
    .category-list{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
    .category-chip{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:999px;background:#f2f4f7;border:1px solid var(--border)}
    .category-chip form{margin:0}
    .shop-category-filter{display:flex;gap:10px;flex-wrap:wrap;margin:0 0 24px}
    .shop-category-filter button{border:1px solid var(--border);background:#fff;padding:9px 14px;border-radius:999px;cursor:pointer;font-weight:700}
    .shop-category-filter button.active{background:var(--primary);color:#fff;border-color:var(--primary)}

    .product-admin-card details{margin-top:12px}
    .product-admin-card summary{cursor:pointer;font-weight:700}
    @media(max-width:1000px){.product-admin-card{grid-template-columns:100px minmax(0,1fr)}.product-admin-card>.product-admin-actions{grid-column:1/-1}.product-admin-thumb{width:100px;height:100px}}



    /* Startseite 6.5 – klares B2B UI/UX-Design */
    .public-links{gap:20px}
    .public-links .nav-cta{padding:11px 17px;border-radius:11px;background:var(--accent);color:#fff;box-shadow:0 8px 22px rgba(244,123,32,.25)}
    .public-links .nav-cta:hover{color:#fff;transform:translateY(-1px)}
    .wc-home h2{font-size:clamp(2rem,3.5vw,3.25rem);line-height:1.08;letter-spacing:-.035em;margin:0 0 18px}
    .wc-home h3{letter-spacing:-.015em}
    .wc-section-head{max-width:760px;margin-bottom:38px}
    .wc-section-head.center{text-align:center;margin-left:auto;margin-right:auto}
    .wc-kicker{display:inline-flex;align-items:center;gap:8px;color:var(--accent);font-size:.78rem;font-weight:900;letter-spacing:.12em;text-transform:uppercase;margin-bottom:14px}
    .wc-kicker:before{content:"";width:24px;height:3px;border-radius:2px;background:var(--accent)}
    .wc-hero-v2{position:relative;overflow:hidden;background:linear-gradient(135deg,#10283f 0%,var(--primary) 58%,#204867 100%);color:#fff;padding:82px 0 92px}
    .wc-hero-v2:before{content:"";position:absolute;width:620px;height:620px;border-radius:50%;right:-210px;top:-280px;background:rgba(244,123,32,.13);filter:blur(2px)}
    .wc-hero-grid{position:relative;display:grid;grid-template-columns:minmax(0,1.02fr) minmax(440px,.98fr);gap:68px;align-items:center}
    .wc-hero-copy h1{font-size:clamp(3rem,5.5vw,5.3rem);line-height:.99;letter-spacing:-.055em;color:#fff;margin:12px 0 24px;max-width:760px}
    .wc-hero-copy>p{font-size:1.14rem;line-height:1.72;color:rgba(255,255,255,.82);max-width:680px;margin:0}
    .wc-hero-actions{display:flex;gap:13px;align-items:center;flex-wrap:wrap;margin-top:31px}
    .wc-hero-actions .btn{min-height:52px;padding:0 21px;display:inline-flex;align-items:center;justify-content:center;border-radius:11px;font-weight:800;text-decoration:none}
    .btn.wc-secondary{background:transparent;border:1px solid rgba(255,255,255,.38);color:#fff}
    .btn.wc-secondary:hover{background:rgba(255,255,255,.09);border-color:#fff}
    .wc-hero-note{display:flex;gap:20px;flex-wrap:wrap;margin-top:26px;color:rgba(255,255,255,.68);font-size:.88rem}
    .wc-hero-note span{display:flex;align-items:center;gap:7px}.wc-hero-note span:before{content:"✓";display:grid;place-items:center;width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,.13);color:#ff9a4a;font-weight:900}
    .wc-browser{position:relative;border:1px solid rgba(255,255,255,.18);border-radius:20px;background:#fff;box-shadow:0 34px 80px rgba(3,14,26,.38);overflow:hidden;transform:rotate(1deg)}
    .wc-browser:after{content:"";position:absolute;inset:auto -40px -55px auto;width:150px;height:150px;background:var(--accent);opacity:.12;border-radius:50%}
    .wc-browser-bar{height:44px;background:#edf1f5;border-bottom:1px solid #dde3e9;display:flex;align-items:center;padding:0 15px;gap:7px}
    .wc-browser-bar i{display:block;width:9px;height:9px;border-radius:50%;background:#b9c3ce}.wc-browser-bar i:first-child{background:#ff8b62}.wc-browser-url{height:22px;flex:1;max-width:230px;border-radius:7px;background:#fff;margin-left:9px;color:#7a8794;font-size:.65rem;padding:5px 9px}
    .wc-app{display:grid;grid-template-columns:112px 1fr;min-height:390px;color:#182433;background:#f6f8fa}
    .wc-app-side{background:#142f49;color:#fff;padding:20px 12px}.wc-app-brand{display:flex;align-items:center;gap:7px;font-size:.69rem;font-weight:900;margin-bottom:27px}.wc-app-mark{width:22px;height:22px;border-radius:6px;background:var(--accent)}
    .wc-app-nav{display:grid;gap:8px}.wc-app-nav span{font-size:.61rem;padding:8px;border-radius:6px;color:rgba(255,255,255,.62)}.wc-app-nav span.active{background:rgba(255,255,255,.1);color:#fff}
    .wc-app-main{padding:23px}.wc-app-top{display:flex;justify-content:space-between;gap:12px;align-items:start}.wc-app-top b{font-size:.86rem}.wc-app-top small{display:block;color:#7b8794;margin-top:4px;font-size:.61rem}.wc-app-avatar{width:29px;height:29px;border-radius:50%;background:#ffe5d1;color:#b54b00;display:grid;place-items:center;font-size:.62rem;font-weight:900}
    .wc-app-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin:20px 0}.wc-stat{padding:12px;border:1px solid #e5e9ed;border-radius:9px;background:#fff}.wc-stat small{display:block;font-size:.55rem;color:#7b8794}.wc-stat b{display:block;margin-top:5px;font-size:.82rem}.wc-stat em{font-style:normal;font-size:.5rem;color:#2d8a60}
    .wc-app-heading{font-size:.7rem;font-weight:800;margin-bottom:9px}.wc-mini-products{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}.wc-mini-product{border:1px solid #e5e9ed;border-radius:9px;background:#fff;overflow:hidden}.wc-mini-shirt{height:64px;background:linear-gradient(145deg,#e9edf0,#f9fafb);display:grid;place-items:center;color:var(--primary)}.wc-mini-shirt svg{width:37px;height:37px}.wc-mini-copy{padding:8px}.wc-mini-copy b{font-size:.52rem;display:block}.wc-mini-copy span{font-size:.48rem;color:#7b8794}.wc-budget{margin-top:15px;padding:12px;border-radius:9px;background:#fff;border:1px solid #e5e9ed}.wc-budget-row{display:flex;justify-content:space-between;font-size:.58rem}.wc-progress{height:6px;background:#edf0f3;border-radius:9px;margin-top:8px;overflow:hidden}.wc-progress span{display:block;width:72%;height:100%;background:var(--accent);border-radius:9px}
    .wc-trust{background:#fff;border-bottom:1px solid var(--border)}.wc-trust-grid{display:grid;grid-template-columns:repeat(4,1fr)}.wc-trust-item{padding:22px 20px;display:flex;align-items:center;justify-content:center;gap:11px;font-size:.85rem;font-weight:800;text-align:center;border-right:1px solid var(--border)}.wc-trust-item:last-child{border-right:0}.wc-trust-item svg{width:21px;height:21px;color:var(--accent);flex:0 0 auto}
    .wc-compare{display:grid;grid-template-columns:1fr 1fr;gap:22px}.wc-compare-card{padding:31px;border-radius:18px;border:1px solid var(--border);background:#fff}.wc-compare-card.before{background:#fafafa;color:#596577}.wc-compare-card.after{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:var(--shadow)}.wc-compare-card h3{font-size:1.35rem;margin:0 0 21px}.wc-check-list{display:grid;gap:13px;margin:0;padding:0;list-style:none}.wc-check-list li{display:flex;gap:11px;align-items:flex-start;line-height:1.5}.wc-check-list li:before{content:"×";display:grid;place-items:center;flex:0 0 23px;height:23px;border-radius:50%;background:#eef0f2;color:#8a95a2;font-weight:900}.wc-compare-card.after .wc-check-list li:before{content:"✓";background:rgba(244,123,32,.18);color:#ff9c52}
    .wc-feature-six{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}.wc-feature-v2{padding:27px;border:1px solid var(--border);border-radius:16px;background:#fff;transition:.2s ease}.wc-feature-v2:hover{transform:translateY(-3px);box-shadow:0 16px 35px rgba(16,24,40,.08);border-color:#d6dce3}.wc-icon{width:43px;height:43px;border-radius:12px;background:#fff0f1;color:var(--accent);display:grid;place-items:center;margin-bottom:19px}.wc-icon svg{width:22px;height:22px}.wc-feature-v2 h3{font-size:1.08rem;margin:0 0 9px}.wc-feature-v2 p{margin:0;color:var(--muted);font-size:.94rem;line-height:1.65}
    .wc-process-v2{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;counter-reset:workstep}.wc-process-v2 .wc-process-item{position:relative;padding:27px;overflow:visible;box-shadow:none}.wc-process-v2 .wc-process-item:before{counter-increment:workstep;content:"0" counter(workstep);display:block;color:var(--accent);font-size:.78rem;font-weight:900;letter-spacing:.08em;margin-bottom:28px}.wc-process-v2 .wc-process-item:not(:last-child):after{content:"";position:absolute;right:-19px;top:41px;width:20px;height:1px;background:#ccd3da;z-index:2}.wc-process-v2 h3{font-size:1.05rem;margin:0 0 10px}.wc-process-v2 p{font-size:.9rem;line-height:1.6;color:var(--muted);margin:0}
    .wc-yw-grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:60px;align-items:center}.wc-yw-panel{padding:38px;border-radius:22px;background:linear-gradient(145deg,var(--primary),#245d73);color:#fff;box-shadow:var(--shadow)}.wc-yw-panel h3{font-size:1.55rem;margin:0 0 11px}.wc-yw-panel p{color:rgba(255,255,255,.76);line-height:1.7}.wc-yw-services{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-top:25px}.wc-yw-services span{padding:13px;border-radius:10px;background:rgba(255,255,255,.08);font-size:.82rem;font-weight:800}.wc-yw-points{display:grid;gap:18px}.wc-yw-point{display:grid;grid-template-columns:44px 1fr;gap:14px}.wc-yw-point strong{width:42px;height:42px;border-radius:12px;background:#fff0f1;color:var(--accent);display:grid;place-items:center}.wc-yw-point h3{margin:1px 0 5px;font-size:1.05rem}.wc-yw-point p{margin:0;color:var(--muted);line-height:1.6}
    .wc-faq{max-width:880px;margin:0 auto;display:grid;gap:11px}.wc-faq details{border:1px solid var(--border);border-radius:13px;background:#fff;padding:0 20px}.wc-faq summary{cursor:pointer;font-weight:800;padding:18px 32px 18px 0;position:relative}.wc-faq summary:after{content:"+";position:absolute;right:0;font-size:1.25rem;color:var(--accent)}.wc-faq details[open] summary:after{content:"−"}.wc-faq details p{color:var(--muted);line-height:1.7;margin:0;padding:0 32px 19px 0}
    .wc-final-cta{position:relative;overflow:hidden;padding:52px;border-radius:22px;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:space-between;gap:30px}.wc-final-cta:after{content:"";position:absolute;width:230px;height:230px;border-radius:50%;right:-90px;top:-100px;background:var(--accent);opacity:.18}.wc-final-cta h2{color:#fff;max-width:700px;margin-bottom:10px}.wc-final-cta p{color:rgba(255,255,255,.72);margin:0}.wc-final-cta .btn{position:relative;z-index:2;white-space:nowrap;padding:15px 21px}
    @media(max-width:1050px){.wc-hero-grid{grid-template-columns:1fr;gap:50px}.wc-browser{max-width:680px;transform:none}.wc-feature-six{grid-template-columns:repeat(2,1fr)}.wc-process-v2{grid-template-columns:repeat(2,1fr)}.wc-process-v2 .wc-process-item:after{display:none}.wc-yw-grid{gap:35px}.public-links{gap:13px}.public-links a{font-size:.88rem}}
    @media(max-width:900px){
      .public-links{display:none}.mobile-menu{display:block}
      .wc-feature-grid,.wc-process-grid,.content-split,.subpage-content-card.with-image{grid-template-columns:1fr}
      .wc-hero{min-height:520px}.subpage-content-card{padding:28px}.wc-trust-grid{grid-template-columns:1fr 1fr}.wc-trust-item:nth-child(2){border-right:0}.wc-trust-item:nth-child(-n+2){border-bottom:1px solid var(--border)}.wc-yw-grid{grid-template-columns:1fr}.marketing-split,.marketing-split.reverse,.contact-layout{grid-template-columns:1fr}.marketing-photo{min-height:420px}.service-grid{grid-template-columns:repeat(2,1fr)}.process-list{grid-template-columns:repeat(2,1fr)}.process-card:nth-child(2){border-right:0}.marketing-cta-grid{align-items:flex-start;flex-direction:column}
    }
    @media(max-width:640px){.wrap{padding-left:18px;padding-right:18px}.public-nav{min-height:70px}.main-site-logo{max-width:185px;max-height:46px}.wc-hero-v2{padding:60px 0 66px}.wc-hero-copy{min-width:0}.wc-hero-copy h1{font-size:clamp(2.35rem,10vw,3.1rem);overflow-wrap:break-word}.wc-hero-actions{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,.85fr)}.wc-hero-actions .btn{min-width:0;padding-left:10px;padding-right:10px;font-size:.82rem;white-space:nowrap}.wc-hero-grid{gap:40px}.wc-browser{margin:0 -8px}.wc-app{grid-template-columns:78px 1fr;min-height:330px}.wc-app-side{padding:15px 8px}.wc-app-main{padding:16px 12px}.wc-app-nav span:nth-child(n+4){display:none}.wc-app-stats{grid-template-columns:1fr 1fr}.wc-app-stats .wc-stat:last-child{display:none}.wc-mini-products{grid-template-columns:1fr 1fr}.wc-mini-product:last-child{display:none}.wc-trust-item{padding:17px 9px;font-size:.72rem}.wc-section{padding:60px 0}.wc-compare,.wc-feature-six,.wc-process-v2{grid-template-columns:1fr}.wc-yw-services{grid-template-columns:1fr}.wc-final-cta{padding:35px 27px;display:block}.wc-final-cta .btn{margin-top:24px}.wc-hero-note{gap:10px}.public-links .nav-cta{font-size:.78rem;padding:9px 11px}}
    @media(max-width:640px){.service-grid,.process-list{grid-template-columns:1fr}.process-card,.process-card+.process-card{padding:23px 0;border-right:0;border-bottom:1px solid var(--border)}.marketing-section{padding:58px 0}.marketing-photo{min-height:360px}.page-hero{padding:62px 0 54px}.contact-panel,.contact-action{padding:27px}}
    @media(max-width:400px){.wc-hero-actions{grid-template-columns:1fr}.wc-hero-actions .btn{width:100%}}
    </style>';
}

function site_brand_html(bool $withName=true,bool $darkSurface=false): string {
    $logo=yw_site_logo_path($darkSurface);
    $title=$withName?current_site_setting('site_title','WorkwearCenter'):'WorkwearCenter';
    return '<div class="site-brand-wrap"><a href="/" aria-label="'.e($title).'"><img class="main-site-logo" src="/'.e($logo).'" alt="'.e($title).'"></a></div>';
}


function maintenance_enabled(): bool {
    return current_site_setting('maintenance_mode','0') === '1';
}

function maintenance_page(): void {
    $primary=strtolower(trim(current_site_setting('site_primary','#fc6904')));
    $accent=strtolower(trim(current_site_setting('site_accent','#fc6904')));
    if(in_array($primary,['#287f9d','#b64f1d'],true))$primary='#fc6904';
    if(in_array($accent,['#c54b55','#c45c27'],true))$accent='#fc6904';
    if(!color_ok($primary))$primary='#fc6904';
    if(!color_ok($accent))$accent='#fc6904';
    $logo=yw_site_logo_path(true);
    $title=current_site_setting('site_title','WorkwearCenter');

    http_response_code(503);
    header('Retry-After: 3600');

    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>'.e($title).' – Wartung</title>';
    echo '<style>
        html,body{height:100%;margin:0}
        body{
            display:grid;
            place-items:center;
            background:
                radial-gradient(circle at 20% 20%, '.e($accent).'22 0, transparent 28%),
                radial-gradient(circle at 80% 80%, '.e($accent).'18 0, transparent 30%),
                #292d2f;
            font-family:Arial,Helvetica,sans-serif;
        }
        .maintenance-wrap{
            width:min(90vw,760px);
            min-height:360px;
            display:grid;
            place-items:center;
            padding:48px;
            box-sizing:border-box;
            border-radius:28px;
            background:rgba(255,255,255,.06);
            border:1px solid rgba(255,255,255,.12);
            box-shadow:0 30px 80px rgba(0,0,0,.22);
            backdrop-filter:blur(10px);
        }
        .maintenance-logo{
            max-width:min(70vw,420px);
            max-height:180px;
            object-fit:contain;
            display:block;
            filter:drop-shadow(0 10px 24px rgba(0,0,0,.18));
        }
        .maintenance-fallback{
            width:120px;height:120px;border-radius:26px;
            background:#292d2f;color:#fc6904;
            display:grid;place-items:center;
            font-size:40px;font-weight:900;
        }
        .maintenance-legal{position:fixed;left:0;right:0;bottom:20px;display:flex;justify-content:center;gap:22px;font-size:14px}
        .maintenance-legal a{color:rgba(255,255,255,.72);text-decoration:none}
        .maintenance-legal a:hover,.maintenance-legal a:focus-visible{color:#fff;text-decoration:underline}
    </style></head><body><main class="maintenance-wrap">';
    echo '<img class="maintenance-logo" src="/'.e($logo).'" alt="'.e($title).'">';
    echo '</main><nav class="maintenance-legal" aria-label="Rechtliches"><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></nav></body></html>';
}


function wc_public_icon(string $name): string {
    $icons=[
        'user'=>'<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
        'budget'=>'<circle cx="12" cy="12" r="9"/><path d="M16 8.5a4 4 0 1 0 0 7M8 11h6M8 14h5"/>',
        'shirt'=>'<path d="m8 3-5 3 2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0Z"/>',
        'check'=>'<path d="m20 6-11 11-5-5"/>',
        'box'=>'<path d="m21 8-9 5-9-5 9-5 9 5Z"/><path d="m3 8 9 5 9-5v9l-9 5-9-5V8Z"/><path d="M12 13v9"/>',
        'layers'=>'<path d="m12 2 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 17l9 5 9-5"/>',
        'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'spark'=>'<path d="m12 3 1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7L12 3ZM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>',
        'home'=>'<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'search'=>'<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'file'=>'<path d="M6 2h8l4 4v16H6z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>',
        'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'history'=>'<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'help'=>'<circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.3 2.3 0 1 1 3.4 2c-.8.5-1.2 1-1.2 2M12 17h.01"/>',
        'building'=>'<path d="M4 21V4h11v17M15 9h5v12M8 8h3M8 12h3M8 16h3M3 21h18"/>',
    ];
    $body=$icons[$name]??$icons['check'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$body.'</svg>';
}



function render_content_settings(): void {
    $v=fn(string $k,string $d='')=>e(array_key_exists($k,yw_content_defaults())?yw_text($k):current_site_setting($k,$d));
    $raw=fn(string $k,string $d='')=>array_key_exists($k,yw_content_defaults())?yw_text($k):current_site_setting($k,$d);

    // 10.19: Startseite und Unterseiten sind seit 10.16.23 fest im Seitendesign hinterlegt. Die früheren Eingabefelder hatten
    // keine Wirkung mehr und wurden entfernt; gespeicherte Altwerte bleiben unverändert in der Datenbank.
    echo '<section class="card"><h2>Texte der Website</h2><p class="muted">Die Texte von Startseite, Leistungen, So funktioniert’s, Über uns, Kontakt und Anfrage sind fest auf das Seitendesign abgestimmt und kommen mit jedem Update. Hier pflegst du Footer, Impressum, die Angaben zur Datenschutzerklärung und den Ansprechpartner auf der Startseite.</p></section>';
    echo '<form method="post" class="settings-form">'.csrf_field().'<input type="hidden" name="action" value="save_content_settings">';

    echo '<details class="settings-group" open><summary>Footer und Impressum</summary><div class="settings-group-inner"><label>Footer-Text<input name="footer_text" value="'.$v('footer_text','WorkwearCenter – ein Service von Yanik Textildruck').'"></label>';
    echo '<div class="grid2"><label>Unternehmen<input name="imprint_company" value="'.$v('imprint_company','Yanik Textildruck').'"></label><label>Inhaber<input name="imprint_owner" value="'.$v('imprint_owner','Jens Yanik').'"></label></div>';
    echo '<div class="grid2"><label>Straße<input name="imprint_street" value="'.$v('imprint_street','Geseker Straße 19').'"></label><label>PLZ / Ort<input name="imprint_city" value="'.$v('imprint_city','33154 Salzkotten').'"></label></div>';
    echo '<div class="grid2"><label>Telefon<input name="imprint_phone" value="'.$v('imprint_phone','05258 / 2090004').'"></label><label>E-Mail<input name="imprint_email" value="'.$v('imprint_email','info@yanik-textildruck.de').'"></label></div>';
    echo '<div class="grid2"><label>Umsatzsteuer-ID <small>Pflichtangabe, falls vorhanden (§ 27a UStG)</small><input name="imprint_vat_id" maxlength="40" placeholder="DE123456789" value="'.$v('imprint_vat_id','').'"></label><label>Handelsregister <small>nur falls eingetragen</small><input name="imprint_register" maxlength="200" placeholder="Amtsgericht Paderborn, HRA 1234" value="'.$v('imprint_register','').'"></label></div>';
    echo '<label>Wirtschafts-Identifikationsnummer <small>nur falls vom Bundeszentralamt für Steuern zugeteilt (§ 5 DDG)</small><input name="imprint_wid" maxlength="40" placeholder="DE123456789-00001" value="'.$v('imprint_wid','').'"></label>';
    echo '<p class="muted">Telefon leer lassen, wenn keine Nummer im Impressum stehen soll. <a href="/impressum" target="_blank" rel="noopener">Impressum ansehen</a></p></div></details>';
    // 10.22: Die Datenschutzerklärung wird aus dem System erzeugt (app/modules/legal.php); hier nur Betreiberangaben, Ergänzungen oder ein eigener Text.
    $mode=current_site_setting('privacy_mode','auto')==='custom'?'custom':'auto';
    echo '<details class="settings-group" open><summary>Datenschutzerklärung</summary><div class="settings-group-inner">';
    echo '<p class="muted">Die Datenschutzerklärung wird automatisch aus den Funktionen des WorkwearCenters erstellt (Cookies, Speicherfristen, Schnittstellen, Firmenshops) und mit jedem Update aktuell gehalten. Verantwortlicher und Kontakt kommen aus dem Impressum oben. <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzerklärung ansehen</a></p>';
    echo '<label>Hoster (Name und Anschrift) <small>steht in der Erklärung als Auftragsverarbeiter</small><input name="privacy_hoster" maxlength="300" value="'.$v('privacy_hoster',YW_DEFAULT_HOSTER).'"></label>';
    echo '<div class="grid2"><label>E-Mail-Versand über <small>leer = derselbe Anbieter wie das Hosting</small><input name="privacy_mail_provider" maxlength="300" placeholder="z. B. Greenmark IT GmbH (do.de)" value="'.$v('privacy_mail_provider','').'"></label>';
    echo '<label>Server-Protokolle werden gelöscht … <small>Angabe des Hosters, z. B. „nach 7 Tagen“</small><input name="privacy_log_retention" maxlength="120" placeholder="nach 7 Tagen" value="'.$v('privacy_log_retention','').'"></label></div>';
    echo '<label>Datenschutzbeauftragter <small>nur falls benannt: Name und Kontakt</small><input name="privacy_dpo" maxlength="300" value="'.$v('privacy_dpo','').'"></label>';
    echo rich_editor_field('privacy_extra','Ergänzende Hinweise (optional, erscheinen als letzter Abschnitt)',$raw('privacy_extra',''),160);
    echo '<fieldset class="choice-row" style="margin-top:16px"><legend>Welcher Text erscheint auf /datenschutz?</legend>';
    echo '<label class="check"><input type="radio" name="privacy_mode" value="auto" '.($mode==='auto'?'checked':'').'><span><strong>Automatisch erstellte Erklärung</strong> (empfohlen)</span></label>';
    echo '<label class="check"><input type="radio" name="privacy_mode" value="custom" '.($mode==='custom'?'checked':'').'><span><strong>Eigener Text</strong>, z. B. von einem Anwalt (wird nur verwendet, wenn er mindestens 250 Zeichen hat)</span></label></fieldset>';
    echo '<details class="details"><summary>Eigenen Text bearbeiten</summary><div class="details-inner">'.rich_editor_field('privacy_body','Eigene Datenschutzerklärung',$raw('privacy_body',''),420).'</div></details>';
    echo '</div></details>';

    echo '<button class="btn primary" type="submit" style="margin-top:20px">Inhalte speichern</button></form>';
    echo rich_editor_script();
    render_trust_settings();
}

function render_design_settings(): void {
    $v=function(string $k,string $d=''): string {
        $value=current_site_setting($k,$d);
        if($k==='site_primary'&&in_array(strtolower(trim($value)),['#287f9d','#b64f1d'],true))$value='#fc6904';
        if($k==='site_accent'&&in_array(strtolower(trim($value)),['#c54b55','#c45c27'],true))$value='#fc6904';
        return e($value);
    };
    $imageField=function(string $key,string $label): string {
        $p=yw_local_image_path(current_site_setting($key,''),['branding'],true);
        $html='<label>'.$label.'<input type="file" name="'.$key.'" accept="image/png,image/jpeg,image/webp"></label>';
        if($p){
            $html.='<div class="editor-image-preview"><img src="/'.e($p).'" alt=""><label class="check"><input type="checkbox" name="remove_'.$key.'" value="1"><span>Bild löschen</span></label></div>';
        }
        return $html;
    };

    echo '<form method="post" enctype="multipart/form-data" class="settings-form">'.csrf_field().'<input type="hidden" name="action" value="save_design_settings">';
    echo '<details class="settings-group" open><summary>Marke und Betrieb</summary><div class="settings-group-inner">';
    echo '<label class="check" style="margin-bottom:16px"><input type="checkbox" name="maintenance_mode" value="1" '.(maintenance_enabled()?'checked':'').'><span><strong>Wartungsmodus</strong><br><small>Öffentliche Seite ausblenden, Admin bleibt erreichbar.</small></span></label>';
    echo '<label>Seitentitel<input name="site_title" maxlength="190" value="'.$v('site_title','WorkwearCenter').'"></label>';
    echo '<div class="grid2"><label>Hauptfarbe<input type="color" name="site_primary" value="'.$v('site_primary','#fc6904').'"></label><label>Akzentfarbe<input type="color" name="site_accent" value="'.$v('site_accent','#fc6904').'"></label></div>';
    echo $imageField('site_logo','Logo');
    echo '</div></details>';
    echo '<details class="settings-group"><summary>Website-Fotos</summary><div class="settings-group-inner"><p class="muted">Empfohlen: echte Arbeiten im Querformat, mindestens 1.600 Pixel breit. Nur hier aufgeführte Bilder werden auf der Website verwendet.</p>'
        .$imageField('hero_image','Startseite – großes Einstiegsbild (am besten hochkant, Nahaufnahme)')
        .$imageField('section1_image','Startseite – Werkstatt, großes Bild (quer)').$imageField('section2_image','Startseite – Werkstatt, zweites Bild (hoch)')
        .$imageField('services_image','Leistungen')
        .$imageField('about_image','Über uns – erstes Bild (hoch)').$imageField('section3_image','Über uns – zweites Bild (hoch)')
        .'</div></details>';

    echo '<button class="btn primary" type="submit" style="margin-top:20px">Design speichern</button></form>';
}



function send_inquiry_mail(array $data): bool {
    $to=current_site_setting('imprint_email','info@yanik-textildruck.de');
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)) $to='info@yanik-textildruck.de';

    $subject='Neue WorkwearCenter-Anfrage: '.$data['company'];
    $body="Neue Anfrage über workwearcenter.de\n\n"
        ."Unternehmen: ".$data['company']."\n"
        ."Ansprechpartner: ".$data['name']."\n"
        ."E-Mail: ".$data['email']."\n"
        ."Telefon: ".$data['phone']."\n"
        ."Mitarbeitende: ".$data['employees']."\n\n"
        .$data['message']."\n"
        .(!empty($data['attachment'])?"\nArtikelliste hochgeladen: in der Verwaltung unter Shop-Anfragen herunterladen (".workwearcenter_url('/admin?tab=inquiries').")\n":'')
        .(!empty($data['probeshop'])?"\nProbeshop mit Logo (30 Tage gültig): ".yw_probeshop_url((string)$data['probeshop'])."\n":'')
        .(isset($data['confirmation'])?"\nEingangsbestätigung an den Interessenten: ".match($data['confirmation']){'ok'=>'gesendet','recipient'=>'nicht gesendet – diese Adresse hat in den letzten 24 Stunden schon eine bekommen (Missbrauchsschutz)','global'=>'nicht gesendet – Stundenlimit für Bestätigungen erreicht (Missbrauchsschutz)',default=>'nicht gesendet – bitte selbst kurz antworten'}."\n":'');

    return send_system_mail($to,$subject,$body,false,(string)$data['email']);
}

/* 10.23: Optionale Artikelliste zur Anfrage. Liegt im privaten Datenordner, nie öffentlich abrufbar,
 * und wird mit der Anfrage gelöscht (manuell oder nach 24 Monaten, siehe yw_inquiry_attachments_prune). */
const YW_INQUIRY_ATTACHMENT_TYPES=['pdf'=>['application/pdf'],'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','application/zip','application/octet-stream'],'xls'=>['application/vnd.ms-excel','application/x-ole-storage','application/CDFV2','application/octet-stream'],'csv'=>['text/plain','text/csv','application/csv','text/x-comma-separated-values'],'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document','application/zip','application/octet-stream'],'doc'=>['application/msword','application/x-ole-storage','application/CDFV2','application/octet-stream'],'png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'webp'=>['image/webp']];

function yw_inquiry_attachment_dir(): string {
    $dir=yw_private_dir().'/anfragen';
    if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Die Artikelliste konnte nicht gespeichert werden.');
    return $dir;
}

/** Prüft die hochgeladene Artikelliste; null, wenn keine gewählt wurde. */
function yw_inquiry_attachment_check(?array $file): ?array {
    if(!$file||(int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
    if((int)$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException(function_exists('upload_error_message')?upload_error_message((int)$file['error'],'Artikelliste'):'Der Upload der Artikelliste ist fehlgeschlagen.');
    $tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);
    if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('Die Artikelliste wurde nicht als gültiger Upload erkannt.');
    if($size<=0||$size>4*1024*1024)throw new RuntimeException('Die Artikelliste darf höchstens 4 MB groß sein.');
    $ext=strtolower((string)pathinfo((string)($file['name']??''),PATHINFO_EXTENSION));
    $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if(!isset(YW_INQUIRY_ATTACHMENT_TYPES[$ext])||!in_array($mime,YW_INQUIRY_ATTACHMENT_TYPES[$ext],true))throw new RuntimeException('Bitte die Artikelliste als PDF, Excel, CSV, Word oder Bild hochladen.');
    return ['tmp'=>$tmp,'ext'=>$ext==='jpeg'?'jpg':$ext];
}

function yw_inquiry_attachment_store(array $checked,int $inquiryId): string {
    $path=yw_inquiry_attachment_dir().'/'.$inquiryId.'-'.bin2hex(random_bytes(12)).'.'.$checked['ext'];
    if(!yw_move_upload($checked['tmp'],$path))throw new RuntimeException('Die Artikelliste konnte nicht gespeichert werden.');
    @chmod($path,0640);
    return $path;
}

/** Gespeicherte Artikelliste einer Anfrage (Pfad oder leer). */
function yw_inquiry_attachment(int $inquiryId): string {
    if($inquiryId<=0)return '';
    try{$files=glob(yw_private_dir().'/anfragen/'.$inquiryId.'-*')?:[];}catch(Throwable $e){return '';}
    foreach($files as $f)if(is_file($f)&&preg_match('/^\d+-[a-f0-9]{24}\.[a-z]{3,4}$/',basename($f)))return $f;
    return '';
}

function yw_inquiry_attachment_delete(int $inquiryId): void { while(($f=yw_inquiry_attachment($inquiryId))!==''){if(!@unlink($f))break;} }

/** Dateien zu nicht mehr vorhandenen Anfragen entfernen (läuft mit der täglichen Aufräumroutine). */
function yw_inquiry_attachments_prune(): void {
    try{
        $dir=yw_private_dir().'/anfragen';if(!is_dir($dir))return;
        $ids=array_map('intval',db()->query('SELECT id FROM inquiries')->fetchAll(PDO::FETCH_COLUMN));$ids=array_flip($ids);
        foreach(glob($dir.'/*-*')?:[] as $f){$id=(int)strtok(basename($f),'-');if($id>0&&!isset($ids[$id]))@unlink($f);}
    }catch(Throwable $e){error_log('WorkwearCenter inquiry attachments prune: '.$e->getMessage());}
}

/** Download der Artikelliste in der Verwaltung (nur angemeldete Administratoren). */
function yw_inquiry_attachment_download(int $inquiryId): never {
    require_admin();
    $f=yw_inquiry_attachment($inquiryId);
    if($f===''){http_response_code(404);simple_message('Datei nicht gefunden','Zu dieser Anfrage gibt es keine Artikelliste mehr.');exit;}
    $ext=strtolower(pathinfo($f,PATHINFO_EXTENSION));
    audit_log('inquiry.attachment_downloaded','inquiry',$inquiryId);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="anfrage-'.$inquiryId.'-artikelliste.'.$ext.'"');
    header('Content-Length: '.(string)filesize($f));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    readfile($f);exit;
}

function inquiry_page(): void {
    $error='';
    $success=false;
    $probeshopToken=null;
    $confirmation='ok';

    if(is_post()){
        verify_csrf();

        // einfacher Bot-Schutz
        if(!empty($_POST['website'])){
            $success=true;
        } else {
            if(login_throttled('public-inquiry')){
                $error='Zu viele Anfragen in kurzer Zeit. Bitte versuche es später erneut oder schreibe uns per E-Mail.';
            }
            record_login_failure('public-inquiry');
            $company=trim((string)($_POST['company']??''));
            $name=trim((string)($_POST['name']??''));
            $email=strtolower(trim((string)($_POST['email']??'')));
            $phone=trim((string)($_POST['phone']??''));
            $employees=max(0,(int)($_POST['employees']??0));
            $message=trim(str_replace("\r",'',(string)($_POST['message']??'')));
            $privacy=!empty($_POST['privacy']);
            // 10.23: Auswahlfelder nur mit bekannten Werten übernehmen; sie stehen vorn in der gespeicherten Nachricht.
            $opt=yw_inquiry_options();
            $industry=in_array((string)($_POST['industry']??''),$opt['industry'],true)?(string)$_POST['industry']:'';
            $current=in_array((string)($_POST['current_wear']??''),$opt['current'],true)?(string)$_POST['current_wear']:'';
            $features=array_values(array_intersect($opt['features'],array_map('strval',(array)($_POST['features']??[]))));

            if($error!==''){}
            elseif(!$company || mb_strlen($company)>190) $error='Bitte einen gültigen Firmennamen eingeben.';
            elseif(!$name || mb_strlen($name)>190) $error='Bitte einen gültigen Ansprechpartner eingeben.';
            elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) $error='Bitte eine gültige E-Mail-Adresse eingeben.';
            elseif(mb_strlen($email)>190 || mb_strlen($phone)>80) $error='Eine Eingabe ist zu lang. Bitte prüfe deine Angaben.';
            elseif($employees>100000) $error='Die angegebene Mitarbeiterzahl ist ungültig.';
            elseif(mb_strlen($message)>10000) $error='Bitte eine Nachricht mit höchstens 10.000 Zeichen eingeben.';
            elseif(!$privacy) $error='Bitte bestätige die Datenschutzhinweise.';
            else {
                $details=array_filter(['Branche'=>$industry,'Arbeitskleidung vorhanden'=>$current,'Gewünschte Funktionen'=>implode(', ',$features)],fn($v)=>$v!=='');
                $stored='';foreach($details as $k=>$v)$stored.=$k.': '.$v."\n";
                $stored.=($stored!==''?"\n":'')."Nachricht:\n".($message!==''?$message:'(keine Nachricht)');
                $logo='';$attachment='';
                try{
                    // Artikelliste zuerst prüfen: Ein ungültiges Format soll nichts speichern.
                    $listFile=yw_inquiry_attachment_check($_FILES['article_list']??null);
                    // Optionales Logo: daraus entsteht ein persönlicher Probeshop (10.11).
                    $logo=yw_probeshop_store_logo($_FILES['logo']??null);
                    db()->beginTransaction();
                    $stmt=db()->prepare('INSERT INTO inquiries(company,name,email,phone,employees,message,status) VALUES(?,?,?,?,?,?,?)');
                    $stmt->execute([$company,$name,$email,$phone,$employees,$stored,'new']);
                    $inquiryId=(int)db()->lastInsertId();
                    if($logo!=='')$probeshopToken=yw_probeshop_create($company,$logo,$inquiryId,'inquiry')['token'];
                    if($listFile)$attachment=yw_inquiry_attachment_store($listFile,$inquiryId);
                    db()->commit();
                    $logo=''; // gehört jetzt zum gespeicherten Probeshop
                    $hasAttachment=$attachment!=='';$attachment=''; // gehört jetzt zur gespeicherten Anfrage
                    // 10.15: Eingangsbestätigung höchstens 1× je Adresse in 24 h und 30× je Stunde, ohne Formulartext
                    $confirmation=yw_inquiry_confirmation_reserve($inquiryId,$email);
                    audit_log('inquiry.created','inquiry',$inquiryId,($probeshopToken!==null?['probeshop'=>true]:[])+($hasAttachment?['attachment'=>true]:[])+($confirmation!=='ok'?['confirmation'=>$confirmation]:[]));
                    send_inquiry_mail([
                        'company'=>$company,'name'=>$name,'email'=>$email,'phone'=>$phone,
                        'employees'=>$employees,'message'=>$stored,'probeshop'=>$probeshopToken,'confirmation'=>$confirmation,'attachment'=>$hasAttachment
                    ]);
                    if($confirmation==='ok')yw_inquiry_confirmation_mail($email,$probeshopToken);
                    $success=true;
                }catch(Throwable $e){
                    if(db()->inTransaction())db()->rollBack();
                    if($logo!=='')yw_probeshop_delete_logo($logo);
                    if($attachment!=='')@unlink($attachment);
                    $probeshopToken=null;
                    if($e instanceof RuntimeException && !$e instanceof PDOException){
                        $error=$e->getMessage().' Du kannst die Anfrage auch ohne diese Datei absenden.';
                    }else{
                        error_log('Public inquiry: '.$e->getMessage());
                        $error='Deine Anfrage konnte gerade nicht gespeichert werden. Bitte versuche es später erneut oder schreibe uns per E-Mail.';
                    }
                }
            }
        }
    }

    site_inquiry_render($success,$error,$probeshopToken,$confirmation==='ok');
}














function render_guide(): void {
    $setup=['Einmalig: Unter „Mein Konto“ die Zwei-Faktor-Anmeldung einrichten und Wiederherstellungscodes sicher ablegen.','Produkte samt eindeutiger Lieferanten-SKU anlegen oder über die WorkwearCenter-CSV importieren.','„Neue Firma“: Firmenname, Kurzname, Lieferadresse, Regeln und Kollektion in einem Schritt anlegen.','In der Firmenansicht der Einrichtungs-Checkliste folgen: Logo, Verantwortlichen anlegen (bekommt automatisch eine Einladung), Mitarbeitende einladen.','Optional: Budget, Mengenlimits, Firmen-PIN und Ausstattungsprofile unter „Ausstattung & Budgets“.','Unter System → „Systemstatus“ alle Prüfungen auf Grün bringen; unter System → „Schnittstellen“ die Auftragszentrale und Lexware Office verbinden (Schlüssel einfügen, die Verbindung wird sofort geprüft).'];
    $daily=['Übersicht öffnen – „Heute zu erledigen“ zeigt alles, was Aufmerksamkeit braucht.','Freigegebene Bestellungen gehen automatisch an die Auftragszentrale; Fehler erscheinen rot unter System → „Schnittstellen“ und auf der Übersicht.','Unter „Produktion“ Beschaffungsbedarf, Motivbestand, Packliste und Produktionsnotizen prüfen.','Status bis Qualitätskontrolle, Versand oder Abholung pflegen; betroffene Mitarbeitende werden automatisch benachrichtigt.','Ausgeschiedene Mitarbeitende über „Daten löschen (DSGVO)“ anonymisieren – Bestellungen bleiben erhalten.'];
    echo '<section class="card"><h2>Firmen-Shop sauber einrichten</h2><div class="grid">';foreach($setup as $n=>$step)echo '<div class="item"><strong>'.($n+1).'.</strong><span>'.e($step).'</span></div>';echo '</div></section>';
    echo '<section class="card"><h2>Täglicher Ablauf</h2><div class="grid">';foreach($daily as $n=>$step)echo '<div class="item"><strong>'.($n+1).'.</strong><span>'.e($step).'</span></div>';echo '</div><div class="flash info space-top"><strong>Sicherheit:</strong> Jeder Nutzer verwendet sein persönliches Konto. Bei einem Personalwechsel wird ausschließlich dieses Konto deaktiviert; gemeinsame Passwörter werden nicht verwendet.</div></section>';
}








/* ---------- 10.11: Vertrauen auf der Startseite (Referenzen, Zitat, Ansprechpartner) ---------- */

/** Gepflegte Kundenlogos: Liste [path, name]; nur gültige Bildpfade. */
function yw_trust_logos(): array {
    $list=json_decode(current_site_setting('trust_logos_json','[]'),true);
    $out=[];
    foreach(is_array($list)?$list:[] as $item){
        if(!is_array($item)||!yw_upload_path_ok((string)($item['path']??''),'branding'))continue;
        $out[]=['path'=>(string)$item['path'],'name'=>mb_substr(trim((string)($item['name']??'')),0,120)];
    }
    return array_slice($out,0,12);
}

/** 10.20: Firmen, deren Shop-Logo als Referenz in der Kundenleiste laufen darf (Admin → Inhalte). */
function yw_trust_company_ids(): array {
    $ids=json_decode(current_site_setting('trust_company_ids','[]'),true);
    return array_values(array_unique(array_filter(array_map('intval',is_array($ids)?$ids:[]),fn(int $id)=>$id>0)));
}

/** Logos der als Referenz markierten, aktiven Firmen (nur wenn ein Shop-Logo hinterlegt ist). */
function yw_trust_company_logos(): array {
    $ids=yw_trust_company_ids();
    if(!$ids)return [];
    try{
        $q=db()->prepare('SELECT id,legal_name,shop_name,logo_path FROM companies WHERE active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY legal_name');
        $q->execute($ids);
    }catch(Throwable $e){return [];}
    $out=[];
    foreach($q->fetchAll() as $company){
        $path=yw_company_logo_path($company);
        if($path!=='')$out[]=['path'=>$path,'name'=>trim((string)$company['legal_name'])?:trim((string)$company['shop_name'])];
    }
    return $out;
}

/** Alle Inhalte des Abschnitts; leer, wenn nichts gepflegt ist. */
function yw_trust_content(): array {
    $t=fn(string $k)=>trim(current_site_setting($k,''));
    $photo=yw_local_image_path($t('trust_contact_photo'),['branding']);
    $c=[
        'title'=>$t('trust_title')?:'Diese Kunden vertrauen uns',
        'logos'=>array_merge(yw_trust_company_logos(),yw_trust_logos()),
        'quote'=>['text'=>$t('trust_quote_text'),'name'=>$t('trust_quote_name'),'company'=>$t('trust_quote_company')],
        'contact'=>['photo'=>$photo,'name'=>$t('trust_contact_name'),'role'=>$t('trust_contact_role'),'text'=>$t('trust_contact_text')],
    ];
    $c['has_quote']=$c['quote']['text']!=='';
    $c['has_contact']=$c['contact']['name']!=='';
    $c['empty']=!$c['logos']&&!$c['has_quote']&&!$c['has_contact'];
    return $c;
}

function render_trust_settings(): void {
    $c=yw_trust_content();$v=fn(string $k)=>e(current_site_setting($k,''));
    echo '<form method="post" enctype="multipart/form-data" class="settings-form trust-settings">'.csrf_field().'<input type="hidden" name="action" value="save_trust_settings"><input type="hidden" name="return_tab" value="content">';
    echo '<details class="settings-group"'.($c['empty']?'':' open').'><summary>Startseite – Vertrauen (Referenzen, Kundenstimme, Ansprechpartner)</summary><div class="settings-group-inner"><p class="muted">Erscheint auf der Startseite nur, wenn mindestens ein Teil gepflegt ist. Nur Logos und Zitate mit Einverständnis der Kunden verwenden.</p>';
    echo '<label>Überschrift<input name="trust_title" maxlength="160" value="'.$v('trust_title').'" placeholder="Diese Kunden vertrauen uns"></label>';
    // 10.20: Kundenfirmen per Haken als Referenz zeigen; das Logo kommt aus ihrem Firmenshop.
    $chosen=yw_trust_company_ids();
    $companies=db()->query('SELECT id,legal_name,shop_name,logo_path,active FROM companies ORDER BY legal_name')->fetchAll();
    echo '<div class="editor-block"><h3>Kundenfirmen als Referenz</h3><p class="muted">Haken setzen, wenn die Firma zugestimmt hat. Ihr Logo aus dem Firmenshop läuft dann in der Leiste „Diese Kunden vertrauen uns“ auf der Startseite. Inaktive Firmen und Firmen ohne Logo erscheinen nicht.</p>';
    if(!$companies)echo '<p class="muted">Noch keine Firmen angelegt.</p>';
    else{
        echo '<div class="trust-company-list" style="display:grid;gap:8px;grid-template-columns:repeat(auto-fill,minmax(260px,1fr))">';
        foreach($companies as $co){
            $logo=yw_company_logo_path($co);$note=!(int)$co['active']?'inaktiv':($logo===''?'kein Logo hinterlegt':'');
            echo '<label class="check" style="display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid #dde1dc;border-radius:10px;background:#fff"><input type="checkbox" name="trust_company_ids[]" value="'.(int)$co['id'].'"'.(in_array((int)$co['id'],$chosen,true)?' checked':'').'>'.($logo!==''?'<img src="/'.e($logo).'" alt="" style="height:28px;max-width:80px;object-fit:contain">':'').'<span>'.e((string)$co['legal_name']).($note!==''?' <small class="muted">('.e($note).')</small>':'').'</span></label>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '<div class="editor-block"><h3>Weitere Kundenlogos hochladen <small class="muted">(max. 12)</small></h3><p class="muted">Für Kunden ohne eigenen Firmenshop. Ab drei Logos laufen sie automatisch durch. Bitte nur Firmenlogos mit Freigabe verwenden.</p>';
    $uploaded=yw_trust_logos();
    if($uploaded){
        echo '<div class="trust-logo-admin">';
        foreach($uploaded as $i=>$logo)echo '<div class="trust-logo-admin-item"><img src="/'.e($logo['path']).'" alt=""><label>Firmenname <small>für Bildbeschreibung</small><input name="trust_logo_name['.$i.']" maxlength="120" value="'.e($logo['name']).'"></label><label class="check"><input type="checkbox" name="trust_logo_remove['.$i.']" value="1"><span>Entfernen</span></label></div>';
        echo '</div>';
    }
    echo '<label>Logos hinzufügen <small>PNG, JPG oder WebP, gern mit transparentem Hintergrund</small><input type="file" name="trust_logo_new[]" multiple accept="image/png,image/jpeg,image/webp"></label></div>';
    echo '<div class="editor-block"><h3>Kundenstimme</h3><label>Zitat<textarea name="trust_quote_text" rows="3" maxlength="600">'.$v('trust_quote_text').'</textarea></label><div class="grid2"><label>Name<input name="trust_quote_name" maxlength="120" value="'.$v('trust_quote_name').'"></label><label>Firma / Funktion<input name="trust_quote_company" maxlength="160" value="'.$v('trust_quote_company').'"></label></div></div>';
    echo '<div class="editor-block"><h3>Dein Ansprechpartner <small class="muted">(leer: Inhaber aus dem Impressum)</small></h3><label>Foto<input type="file" name="trust_contact_photo" accept="image/png,image/jpeg,image/webp"></label>';
    if($c['contact']['photo']!=='')echo '<div class="editor-image-preview"><img src="/'.e($c['contact']['photo']).'" alt=""><label class="check"><input type="checkbox" name="remove_trust_contact_photo" value="1"><span>Foto löschen</span></label></div>';
    echo '<div class="grid2"><label>Name<input name="trust_contact_name" maxlength="120" value="'.$v('trust_contact_name').'"></label><label>Rolle<input name="trust_contact_role" maxlength="120" value="'.$v('trust_contact_role').'" placeholder="z. B. Inhaber, Yanik Textildruck"></label></div><label>Kurzer Satz<input name="trust_contact_text" maxlength="300" value="'.$v('trust_contact_text').'" placeholder="z. B. Ich berate dich persönlich, vom ersten Muster bis zur Lieferung."></label></div>';
    // 10.23: Fallbeispiel auf der Startseite („Kunden, die WorkwearCenter einsetzen“).
    $case=yw_case_study();$cv=fn(string $k)=>e(current_site_setting($k,''));
    echo '<div class="editor-block"><h3>Fallbeispiel auf der Startseite</h3><p class="muted">Ohne den Haken „Kunde hat zugestimmt“ erscheint das Fallbeispiel ohne Firmennamen (Bezeichnung ohne Namen). Leere Felder zeigen den vorbereiteten Text in Grau.</p>';
    echo '<label class="check"><input type="checkbox" name="case_show" value="1"'.($case['show']?' checked':'').'><span>Fallbeispiel auf der Startseite zeigen</span></label>';
    echo '<label class="check"><input type="checkbox" name="case_named" value="1"'.($case['named']?' checked':'').'><span>Kunde hat zugestimmt: Firmennamen zeigen</span></label>';
    echo '<div class="grid2"><label>Firmenname <small>nur mit Zustimmung sichtbar</small><input name="case_company" maxlength="160" value="'.$cv('case_company').'" placeholder="Pader Personal GmbH"></label><label>Bezeichnung ohne Namen<input name="case_anon" maxlength="160" value="'.$cv('case_anon').'" placeholder="Ein Personaldienstleister aus der Region"></label></div>';
    echo '<label>Branche<input name="case_industry" maxlength="120" value="'.$cv('case_industry').'" placeholder="Zeitarbeit und Personaldienstleistung"></label>';
    echo '<label>Ausgangssituation<textarea name="case_situation" rows="2" maxlength="600" placeholder="Viele Mitarbeitende benötigen regelmäßig Arbeitskleidung, oft kurzfristig und in wechselnden Größen.">'.$cv('case_situation').'</textarea></label>';
    echo '<label>Lösung<textarea name="case_solution" rows="2" maxlength="600" placeholder="Ein eigener WorkwearCenter-Shop mit der abgestimmten Kollektion und dem Firmenlogo.">'.$cv('case_solution').'</textarea></label>';
    echo '<label>Eingesetzte Funktionen <small>eine pro Zeile</small><textarea name="case_features" rows="4" maxlength="600" placeholder="Mitarbeiterzugänge&#10;Produktfreigaben&#10;Budgets&#10;Zentrale Bestellung">'.$cv('case_features').'</textarea></label>';
    echo '<label>Ergebnis<textarea name="case_result" rows="2" maxlength="600" placeholder="Einfachere Nachbestellung und weniger Verwaltungsaufwand.">'.$cv('case_result').'</textarea></label></div>';
    echo '<button class="btn primary" type="submit">Vertrauensbereich speichern</button></div></details></form>';
}

/** Hochgeladene Mehrfachdateien in Einzel-Arrays zerlegen. */
function yw_files_list(array $files): array {
    $out=[];
    foreach((array)($files['name']??[]) as $i=>$name)$out[]=['name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];
    return $out;
}

function yw_save_trust_settings(): void {
    $text=function(string $k,int $max): string{
        $v=trim(str_replace("\r",'',(string)($_POST[$k]??'')));
        if(mb_strlen($v)>$max)throw new RuntimeException('Der Inhalt im Feld „'.str_replace(['trust_','_'],['',' '],$k).'“ ist zu lang.');
        return $v;
    };
    $fields=['trust_title'=>$text('trust_title',160),'trust_quote_text'=>$text('trust_quote_text',600),'trust_quote_name'=>$text('trust_quote_name',120),'trust_quote_company'=>$text('trust_quote_company',160),'trust_contact_name'=>$text('trust_contact_name',120),'trust_contact_role'=>$text('trust_contact_role',120),'trust_contact_text'=>$text('trust_contact_text',300),
        'case_show'=>!empty($_POST['case_show'])?'1':'0','case_named'=>!empty($_POST['case_named'])?'1':'','case_company'=>$text('case_company',160),'case_anon'=>$text('case_anon',160),'case_industry'=>$text('case_industry',120),'case_situation'=>$text('case_situation',600),'case_solution'=>$text('case_solution',600),'case_features'=>$text('case_features',600),'case_result'=>$text('case_result',600)];
    $logos=[];$removed=[];$new=[];
    try{
        foreach(yw_trust_logos() as $i=>$logo){
            if(!empty($_POST['trust_logo_remove'][$i])){$removed[]=$logo['path'];continue;}
            $logos[]=['path'=>$logo['path'],'name'=>mb_substr(trim((string)($_POST['trust_logo_name'][$i]??$logo['name'])),0,120)];
        }
        foreach(yw_files_list((array)($_FILES['trust_logo_new']??[])) as $file){
            if((int)$file['error']===UPLOAD_ERR_NO_FILE)continue;
            if(count($logos)>=12)throw new RuntimeException('Es sind höchstens 12 Kundenlogos möglich.');
            // Die Datei kommt einzeln durch dieselbe Prüfung wie alle Website-Bilder.
            $path=upload_site_image($file,'','referenz');$new[]=$path;
            $logos[]=['path'=>$path,'name'=>''];
        }
        $oldPhoto=current_site_setting('trust_contact_photo','');
        $base=!empty($_POST['remove_trust_contact_photo'])?'':$oldPhoto;
        $photo=upload_site_image($_FILES['trust_contact_photo']??null,$base,'ansprechpartner');
        if($photo!==$base&&$photo!=='')$new[]=$photo;
        $fields['trust_contact_photo']=$photo;
        $fields['trust_logos_json']=json_encode($logos,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $wanted=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['trust_company_ids']??[])),fn(int $id)=>$id>0)));
        if($wanted){
            $q=db()->prepare('SELECT id FROM companies WHERE id IN ('.implode(',',array_fill(0,count($wanted),'?')).')');$q->execute($wanted);
            $wanted=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));sort($wanted);
        }
        $fields['trust_company_ids']=json_encode($wanted);
        db()->beginTransaction();
        foreach($fields as $k=>$val)set_site_setting($k,(string)$val);
        db()->commit();
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        foreach($new as $path)delete_site_image($path);
        throw $e;
    }
    foreach($removed as $path)delete_site_image($path);
    if($oldPhoto!==''&&$oldPhoto!==$fields['trust_contact_photo'])delete_site_image($oldPhoto);
    audit_log('site.trust_updated','site',null,['logos'=>count($logos),'companies'=>json_decode($fields['trust_company_ids'],true)]);
}
