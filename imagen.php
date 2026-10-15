<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/catalog.php';
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
try {
    $id=filter_var($_GET['id']??0,FILTER_VALIDATE_INT);
    $variant=$_GET['variant']??'web';
    if(!$id||!is_string($variant)||!in_array($variant,['thumbnail','web','zoom'],true)) { http_response_code(404); exit; }
    // Se valida la publicación de la pieza incluso con sesión administrativa abierta.
    $preview=isset($_GET['preview']) && $_GET['preview']==='1';
    if($preview&&!admin()) { http_response_code(404); exit; }
    $source=$preview?'pieces':'public_pieces';
    $row=query('SELECT v.storage_key,v.mime_type FROM image_variants v JOIN images i ON i.id=v.image_id JOIN '.$source.' p ON p.id=i.piece_id WHERE i.id=? AND v.variant=? AND v.storage_backend=?'.($preview?' AND p.deleted_at IS NULL':''),[$id,$variant,'local'])->fetch();
    if(!$row) { http_response_code(404); exit; }
    $path=image_key($row['storage_key']);
    if(!is_file($path)) { http_response_code(404); exit; }
    header('Content-Type: '.($row['mime_type']==='image/webp'?'image/webp':'image/jpeg')); header('X-Content-Type-Options: nosniff'); header('Content-Length: '.filesize($path));
    session_write_close(); if($_SERVER['REQUEST_METHOD']!=='HEAD') readfile($path);
} catch(Throwable $e) { report_error($e); http_response_code(503); }
