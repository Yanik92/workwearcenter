<?php
declare(strict_types=1);

/*
 * Erzeugt app/data/dateien.json: Prüfsummen aller Programmdateien des Release.
 * Der Systemstatus vergleicht damit die Dateien auf dem Server und meldet,
 * welche beim FTP-Upload übersprungen wurden oder unvollständig angekommen sind.
 *
 * Vor jedem Release ausführen:  php tools/dateiliste-erzeugen.php
 */

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/functions.php';

$root=dirname(__DIR__);
$files=[];
exec('git -C '.escapeshellarg($root).' ls-files',$tracked,$code);
if($code!==0)fwrite(STDERR,"Hinweis: kein Git – Dateien werden aus dem Ordner gelesen.\n");
if($code!==0){
    $tracked=[];
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
    foreach($it as $f)if($f->isFile())$tracked[]=substr($f->getPathname(),strlen($root)+1);
}
foreach($tracked as $path){
    $path=str_replace('\\','/',$path);
    if(!preg_match('#^(assets/|app/(?!private/|data/dateien\.json$)|[^/]+\.php$|\.htaccess$|\.user\.ini$|VERSION$)#',$path))continue;
    if($path==='config.php'||!is_file($root.'/'.$path))continue;
    $files[$path]=yw_file_checksum($root.'/'.$path);
}
ksort($files,SORT_STRING);
$version=trim((string)file_get_contents($root.'/VERSION'));
file_put_contents($root.'/app/data/dateien.json',json_encode(['version'=>$version,'files'=>$files],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo count($files)." Dateien für Version $version in app/data/dateien.json eingetragen.\n";
