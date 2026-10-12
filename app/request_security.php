<?php
declare(strict_types=1);

/** Prüft IPv4- oder IPv6-Adressen gegen eine Adresse oder ein CIDR-Netz. */
function yw_ip_in_network(string $ip,string $network): bool {
    $parts=explode('/',trim($network),2);
    $address=@inet_pton(trim($parts[0]));
    $candidate=@inet_pton(trim($ip));
    if($address===false||$candidate===false||strlen($address)!==strlen($candidate))return false;
    $bits=isset($parts[1])?(ctype_digit($parts[1])?(int)$parts[1]:-1):strlen($address)*8;
    if($bits<0||$bits>strlen($address)*8)return false;
    $whole=intdiv($bits,8);
    for($i=0;$i<$whole;$i++)if($address[$i]!==$candidate[$i])return false;
    $remaining=$bits%8;
    if($remaining>0){$mask=(0xff<<(8-$remaining))&0xff;if((ord($address[$whole])&$mask)!==(ord($candidate[$whole])&$mask))return false;}
    return true;
}

/** Proxy-Header nur akzeptieren, wenn die direkte Verbindung von einem vertrauten Proxy kommt. */
function yw_request_is_https(?array $runtimeConfig=null): bool {
    if(in_array(strtolower(trim((string)($_SERVER['HTTPS']??''))),['on','1','https'],true))return true;
    $runtimeConfig??=is_array($GLOBALS['config']??null)?$GLOBALS['config']:[];
    $remote=(string)($_SERVER['REMOTE_ADDR']??'');
    $trusted=$runtimeConfig['trusted_proxies']??[];
    if(!is_array($trusted))return false;
    $isTrusted=false;
    foreach($trusted as $network){
        if(is_string($network)&&yw_ip_in_network($remote,$network)){$isTrusted=true;break;}
    }
    return $isTrusted&&strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??'')))==='https';
}
