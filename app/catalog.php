<?php
declare(strict_types=1);
require_once __DIR__.'/presentation.php';
const PUBLICATION = ['draft'=>'Borrador','published'=>'Publicado','archived'=>'Archivado'];
const AVAILABILITY = ['available'=>'Disponible','reserved'=>'Reservado','sold'=>'Vendido','not_for_sale'=>'Fuera de venta'];
const IMAGE_ROLES = ['presentation'=>'Presentación','descriptive'=>'Descriptiva','detail'=>'Detalle'];
function get_piece(int $id): array {
    $piece = query('SELECT p.*, v.physical_location,v.internal_notes FROM pieces p LEFT JOIN piece_private v ON v.piece_id=p.id WHERE p.id=?', [$id])->fetch();
    if (!$piece) throw new InvalidArgumentException('La pieza no existe.');
    return $piece;
}
function piece_images(int $id): array {
    return query('SELECT i.*, (c.image_id=i.id) AS is_cover FROM images i LEFT JOIN piece_cover c ON c.piece_id=i.piece_id WHERE i.piece_id=? ORDER BY i.position,i.id', [$id])->fetchAll();
}
function piece_version(int $id): string {
    return hash('sha256', json_encode([
        get_piece($id), piece_images($id), presentation_get($id),
        query('SELECT category_id FROM piece_categories WHERE piece_id=? ORDER BY category_id',[$id])->fetchAll(),
        query('SELECT * FROM piece_field_values WHERE piece_id=? ORDER BY category_id,field_key',[$id])->fetchAll(),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}
function image_key(string $key): string {
    if (!preg_match('~^[a-f0-9]{32}/(thumbnail|web|zoom)\.jpg$~D', $key)) throw new RuntimeException('Clave de imagen inválida.');
    return STORAGE . '/images/' . $key;
}
function remove_generated(array $batch): void {
    foreach ($batch as $item) {
        foreach ($item['variants'] as $variant) { $file = image_key($variant['key']); if (is_file($file)) unlink($file); }
        $dir = STORAGE . '/images/' . $item['token']; if (is_dir($dir)) rmdir($dir);
    }
}
function prepare_uploads(): array {
    $files = $_FILES['photos'] ?? null; $batch = [];
    if (!$files) return [];
    if (!is_array($files['error'])) throw new InvalidArgumentException('Formato de carga incorrecto.');
    if (count($files['error']) > 12) throw new InvalidArgumentException('Subí hasta 12 fotos por vez.');
    try {
        foreach ($files['error'] as $i => $error) {
            if ($error === UPLOAD_ERR_NO_FILE) continue;
            if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Una foto no pudo subirse. Revisá upload_max_filesize y post_max_size en PHP.');
            if (!function_exists('imagecreatefromstring')) throw new InvalidArgumentException('Activá la extensión GD en PHP para procesar fotos.');
            $tmp = $files['tmp_name'][$i];
            if (!is_uploaded_file($tmp) || filesize($tmp) > 15 * 1024 * 1024) throw new InvalidArgumentException('Cada foto puede pesar hasta 15 MB.');
            $info = @getimagesize($tmp);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP], true)) throw new InvalidArgumentException('Usá fotos JPG, PNG o WebP.');
            if ($info[0] * $info[1] > 12000000) throw new InvalidArgumentException('La foto supera 12 megapíxeles. Prepará una copia de menor tamaño para la web.');
            $source = @imagecreatefromstring(file_get_contents($tmp));
            if (!$source) throw new InvalidArgumentException('No se pudo leer una imagen.');
            try {
                if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                    $exif = @exif_read_data($tmp); $orientation = (int)($exif['Orientation'] ?? 1);
                    if (in_array($orientation,[2,4,5,7],true)) imageflip($source, IMG_FLIP_HORIZONTAL);
                    $angle = match ($orientation) {3,4=>180,5,6=>-90,7,8=>90,default=>0};
                    if ($angle) { $rotated = imagerotate($source,$angle,0); imagedestroy($source); $source=$rotated; }
                }
                ensure_storage(); $token=bin2hex(random_bytes(16));
                $dir = STORAGE . '/images/' . $token;
                if (!mkdir($dir,0770,true)) throw new RuntimeException('No se pudo guardar la imagen.');
                $batch[]=['token'=>$token,'variants'=>[]]; $index=count($batch)-1;
                foreach (['thumbnail'=>420,'web'=>1600,'zoom'=>2400] as $variant=>$limit) {
                    $scale=min(1,$limit/max(imagesx($source),imagesy($source)));
                    $w=max(1,(int)round(imagesx($source)*$scale)); $hh=max(1,(int)round(imagesy($source)*$scale));
                    $canvas=imagecreatetruecolor($w,$hh); imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
                    imagecopyresampled($canvas,$source,0,0,0,0,$w,$hh,imagesx($source),imagesy($source));
                    $key=$token . '/' . $variant . '.jpg'; $path=image_key($key);
                    try { if (!imagejpeg($canvas,$path,$variant==='thumbnail'?82:90)) throw new RuntimeException('Falló la escritura de imagen.'); }
                    finally { imagedestroy($canvas); }
                    $batch[$index]['variants'][$variant]=['key'=>$key,'width'=>$w,'height'=>$hh,'bytes'=>filesize($path)];
                }
            } finally { imagedestroy($source); }
        }
        return $batch;
    } catch (Throwable $e) { remove_generated($batch); throw $e; }
}
function save_piece(int $id): int {
    $title=text_input('title',255) ?: 'Sin título';
    $categoryIds=$_POST['categories'] ?? [];
    if (!is_array($categoryIds)) throw new InvalidArgumentException('Revisá las categorías.');
    $categoryIds=array_values(array_unique(array_map('intval',$categoryIds)));
    if (!$categoryIds) throw new InvalidArgumentException('Elegí al menos una categoría.');
    $validIds=array_column(query('SELECT id FROM categories')->fetchAll(),'id');
    foreach ($categoryIds as $cat) if (!in_array($cat,array_map('intval',$validIds),true)) throw new InvalidArgumentException('Categoría inexistente.');
    $type=choice('unit_type',['object','set'],'object');
    $count=$type==='set'?optional_integer('component_count',1,1000000):null;
    $status=choice('publication_status',array_keys(PUBLICATION),'draft');
    $availability=choice('availability',array_keys(AVAILABILITY),'not_for_sale');
    $certainty=choice('dating_certainty',['unknown','estimated','documented'],'unknown');
    $from=optional_integer('year_from',-32768,32767); $to=optional_integer('year_to',-32768,32767);
    if ($from!==null && $to!==null && $from>$to) throw new InvalidArgumentException('El año inicial no puede ser posterior al final.');
    $data=[$title,$type,$count,$status,$availability,text_input('description'),text_input('dating_label',255),$from,$to,$certainty,text_input('dimensions_label',255),text_input('condition_description'),text_input('provenance_public')];
    $location=text_input('physical_location',255); $notes=text_input('internal_notes');
    $postedFields=$_POST['fields'] ?? [];
    if (!is_array($postedFields)) throw new InvalidArgumentException('Campos de categoría incorrectos.');
    $definitions=query('SELECT * FROM category_fields')->fetchAll(); $values=[];
    foreach ($definitions as $def) {
        if (!in_array((int)$def['category_id'],$categoryIds,true)) continue;
        $v=$postedFields[$def['category_id']][$def['field_key']] ?? null;
        if ($v===null) continue;
        if (!is_string($v) || mb_strlen($v)>10000) throw new InvalidArgumentException('Revisá ' . $def['label'] . '.');
        $v=trim($v);
        if ($v!=='' && $def['value_type']==='number' && !is_numeric($v)) throw new InvalidArgumentException($def['label'] . ' requiere un número.');
        if ($v!=='' && $def['value_type']==='boolean' && !in_array($v,['0','1'],true)) throw new InvalidArgumentException($def['label'] . ' requiere 0 o 1.');
        if ($v!=='' && $def['value_type']==='date') {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$v);
            if (!$date || $date->format('Y-m-d')!==$v) throw new InvalidArgumentException($def['label'] . ' requiere fecha AAAA-MM-DD.');
        }
        $values[]=[$def['category_id'],$def['field_key'],$v];
    }
    $presentation=presentation_ready()?presentation_input():null;
    $batch=prepare_uploads(); $deletedFiles=[];
    try {
        db()->beginTransaction();
        if ($id) {
            query('SELECT id FROM pieces WHERE id=? FOR UPDATE',[$id]); $existing=get_piece($id);
            if ($existing['deleted_at']) throw new InvalidArgumentException('Restaurá la pieza antes de editarla.');
            // No sobrescribir silenciosamente un cambio realizado en otra pestaña.
            if (piece_version($id) !== text_input('version',64)) throw new InvalidArgumentException('La ficha cambió en otra pestaña. Recargá antes de guardar.');
            if (query("SELECT id FROM transactions WHERE piece_id=? AND status IN ('reserved','completed')",[$id])->fetch() && $availability!==$existing['availability']) throw new InvalidArgumentException('La disponibilidad está vinculada a una operación registrada.');
            query('UPDATE pieces SET title=?,unit_type=?,component_count=?,publication_status=?,availability=?,description=?,dating_label=?,year_from=?,year_to=?,dating_certainty=?,dimensions_label=?,condition_description=?,provenance_public=? WHERE id=?',[...$data,$id]);
        } else {
            $temp='draft-'.bin2hex(random_bytes(16));
            query('INSERT INTO pieces (title,unit_type,component_count,publication_status,availability,description,dating_label,year_from,year_to,dating_certainty,dimensions_label,condition_description,provenance_public,code,slug) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[...$data,$temp,$temp]);
            $id=(int)db()->lastInsertId(); $code='PR-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
            query('UPDATE pieces SET code=?,slug=? WHERE id=?',[$code,strtolower($code),$id]);
        }
        $oldCats=array_map('intval',array_column(query('SELECT category_id FROM piece_categories WHERE piece_id=?',[$id])->fetchAll(),'category_id'));
        $removed=array_diff($oldCats,$categoryIds);
        if ($removed && empty($_POST['confirm_category_removal'])) {
            foreach ($removed as $cat) if (query('SELECT field_key FROM piece_field_values WHERE piece_id=? AND category_id=?',[$id,$cat])->fetch()) throw new InvalidArgumentException('Quitar una categoría eliminará sus datos específicos. Marcá la confirmación o conservá la categoría.');
        }
        foreach ($removed as $cat) {
            query('DELETE FROM piece_field_values WHERE piece_id=? AND category_id=?',[$id,$cat]);
            query('DELETE FROM piece_categories WHERE piece_id=? AND category_id=?',[$id,$cat]);
        }
        foreach (array_diff($categoryIds,$oldCats) as $cat) query('INSERT INTO piece_categories VALUES (?,?)',[$id,$cat]);
        foreach ($values as [$cat,$key,$value]) {
            if ($value==='') query('DELETE FROM piece_field_values WHERE piece_id=? AND category_id=? AND field_key=?',[$id,$cat,$key]);
            else query('INSERT INTO piece_field_values (piece_id,category_id,field_key,value_text) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE value_text=VALUES(value_text)',[$id,$cat,$key,$value]);
        }
        query('INSERT INTO piece_private (piece_id,physical_location,internal_notes) VALUES (?,?,?) ON DUPLICATE KEY UPDATE physical_location=VALUES(physical_location),internal_notes=VALUES(internal_notes)',[$id,$location,$notes]);
        $removedImages=$_POST['remove_images']??[];
        if(!is_array($removedImages)) throw new InvalidArgumentException('Revisá las fotos a eliminar.');
        $removedImages=array_values(array_unique(array_map('intval',$removedImages)));
        $images=piece_images($id);
        $imageIds=array_map('intval',array_column($images,'id'));
        foreach($removedImages as $imageId) {
            if(!in_array($imageId,$imageIds,true)) throw new InvalidArgumentException('Una foto a eliminar no pertenece a esta pieza.');
            foreach(query('SELECT storage_key FROM image_variants WHERE image_id=?',[$imageId])->fetchAll() as $row) { image_key($row['storage_key']); $deletedFiles[]=$row['storage_key']; }
            query('DELETE FROM piece_cover WHERE piece_id=? AND image_id=?',[$id,$imageId]);
            query('DELETE FROM image_variants WHERE image_id=?',[$imageId]);
            query('DELETE FROM images WHERE id=? AND piece_id=?',[$imageId,$id]);
        }
        $images=array_values(array_filter($images,fn($image)=>!in_array((int)$image['id'],$removedImages,true)));
        $validImages=array_map('intval',array_column($images,'id'));
        $imageMeta=$_POST['image_meta'] ?? [];
        if (!is_array($imageMeta)) throw new InvalidArgumentException('Datos de fotos incorrectos.');
        foreach ($images as $image) {
            $meta=$imageMeta[$image['id']] ?? null; if (!$meta) continue;
            if (!is_array($meta) || !is_string($meta['alt'] ?? null) || mb_strlen($meta['alt'])>500 || !is_string($meta['caption'] ?? null) || mb_strlen($meta['caption'])>10000) throw new InvalidArgumentException('Revisá los textos de las fotos.');
            $role=$meta['role']??''; $position=filter_var($meta['position']??'',FILTER_VALIDATE_INT);
            if (!isset(IMAGE_ROLES[$role]) || $position===false || $position<0 || $position>1000000) throw new InvalidArgumentException('Revisá orden y función de las fotos.');
            query('UPDATE images SET role=?,position=?,alt_text=?,caption=? WHERE id=? AND piece_id=?',[$role,$position,trim($meta['alt'])?:$title,trim($meta['caption']),$image['id'],$id]);
        }
        $next=count($images)?max(array_column($images,'position'))+1:1;
        $firstNew=null;
        foreach ($batch as $item) {
            query('INSERT INTO images (piece_id,position,alt_text) VALUES (?,?,?)',[$id,$next++,$title]);
            $imageId=(int)db()->lastInsertId(); $firstNew??=$imageId; $validImages[]=$imageId;
            foreach ($item['variants'] as $variant=>$v) query('INSERT INTO image_variants (image_id,variant,storage_key,mime_type,width,height,byte_size) VALUES (?,?,?,?,?,?,?)',[$imageId,$variant,$v['key'],'image/jpeg',$v['width'],$v['height'],$v['bytes']]);
        }
        $cover=(int)($_POST['cover']??0);
        if(in_array($cover,$removedImages,true)) $cover=0;
        if (!$cover) $cover=(int)($firstNew??($images[0]['id']??0));
        if ($cover && !in_array($cover,$validImages,true)) throw new InvalidArgumentException('La portada debe ser una foto de esta pieza.');
        if ($cover) query('INSERT INTO piece_cover VALUES (?,?) ON DUPLICATE KEY UPDATE image_id=VALUES(image_id)',[$id,$cover]);
        if ($status==='published') {
            if (!$cover || !query("SELECT image_id FROM image_variants WHERE image_id=? AND variant='web'",[$cover])->fetch()) throw new InvalidArgumentException('Para publicar, subí al menos una foto y elegí portada.');
            query('UPDATE pieces SET published_at=COALESCE(published_at,CURRENT_TIMESTAMP) WHERE id=?',[$id]);
        }
        if($presentation!==null) presentation_save($id,$presentation);
        db()->commit();
        foreach($deletedFiles as $key) { $path=image_key($key); if(is_file($path)) @unlink($path); $dir=dirname($path); if(is_dir($dir)&&count(@scandir($dir)?:[])===2) @rmdir($dir); }
        return $id;
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); remove_generated($batch); throw $e; }
}
function trash_piece(int $id, bool $restore): void {
    db()->beginTransaction();
    try {
        query('SELECT id FROM pieces WHERE id=? FOR UPDATE',[$id]); get_piece($id);
        if (!$restore && query("SELECT id FROM transactions WHERE piece_id=? AND status='reserved'",[$id])->fetch()) throw new InvalidArgumentException('Cancelá la reserva antes de enviar la pieza a papelera.');
        query('UPDATE pieces SET deleted_at=' . ($restore?'NULL':'CURRENT_TIMESTAMP') . ' WHERE id=?',[$id]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}
function delete_image(int $pieceId, int $imageId): void {
    db()->beginTransaction(); $keys=[];
    try {
        query('SELECT id FROM pieces WHERE id=? FOR UPDATE',[$pieceId]); $piece=get_piece($pieceId);
        $image=query('SELECT * FROM images WHERE id=? AND piece_id=?',[$imageId,$pieceId])->fetch();
        if (!$image) throw new InvalidArgumentException('La foto no pertenece a esta pieza.');
        if ($piece['deleted_at']) throw new InvalidArgumentException('Restaurá primero la ficha.');
        $isCover=query('SELECT image_id FROM piece_cover WHERE piece_id=? AND image_id=?',[$pieceId,$imageId])->fetch();
        if ($isCover && $piece['publication_status']==='published') throw new InvalidArgumentException('Elegí y guardá otra portada, o pasá la ficha a borrador, antes de eliminar esta foto.');
        $keys=array_column(query('SELECT storage_key FROM image_variants WHERE image_id=?',[$imageId])->fetchAll(),'storage_key');
        foreach ($keys as $key) image_key($key);
        if ($isCover) query('DELETE FROM piece_cover WHERE piece_id=?',[$pieceId]);
        query('DELETE FROM image_variants WHERE image_id=?',[$imageId]); query('DELETE FROM images WHERE id=?',[$imageId]);
        query('UPDATE pieces SET updated_at=CURRENT_TIMESTAMP WHERE id=?',[$pieceId]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    foreach ($keys as $key) { $path=image_key($key); if (is_file($path)) unlink($path); }
    if ($keys) { $dir=dirname(image_key($keys[0])); if (is_dir($dir)) rmdir($dir); }
}

function duplicate_piece(int $sourceId,bool $withImages): int {
    $copies=[];
    try {
        db()->beginTransaction();
        query('SELECT id FROM pieces WHERE id=? FOR UPDATE',[$sourceId]);
        $source=get_piece($sourceId);
        if($source['deleted_at']) throw new InvalidArgumentException('Restaurá la pieza antes de duplicarla.');
        $columns=['title','unit_type','component_count','description','dating_label','year_from','year_to','dating_certainty','dimensions_label','condition_description','provenance_public','price_mode','price_amount','price_currency'];
        $values=array_map(fn($key)=>$source[$key],$columns);
        $values[0]=mb_substr($source['title'],0,246).' (copia)';
        $temporary='draft-'.bin2hex(random_bytes(16));
        query('INSERT INTO pieces ('.implode(',',$columns).',code,slug,publication_status,availability) VALUES ('.implode(',',array_fill(0,count($columns)+4,'?')).')',[...$values,$temporary,$temporary,'draft','not_for_sale']);
        $id=(int)db()->lastInsertId(); $code='PR-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
        query('UPDATE pieces SET code=?,slug=? WHERE id=?',[$code,strtolower($code),$id]);
        query('INSERT INTO piece_categories (piece_id,category_id) SELECT ?,category_id FROM piece_categories WHERE piece_id=?',[$id,$sourceId]);
        query('INSERT INTO piece_field_values (piece_id,category_id,field_key,value_text) SELECT ?,category_id,field_key,value_text FROM piece_field_values WHERE piece_id=?',[$id,$sourceId]);
        query('INSERT INTO piece_terms (piece_id,term_id) SELECT ?,term_id FROM piece_terms WHERE piece_id=?',[$id,$sourceId]);
        query('INSERT INTO piece_private (piece_id,physical_location,acquired_on,acquisition_cost,cost_currency,acquisition_source,internal_notes) SELECT ?,physical_location,acquired_on,acquisition_cost,cost_currency,acquisition_source,internal_notes FROM piece_private WHERE piece_id=?',[$id,$sourceId]);
        if(presentation_ready()) presentation_save($id,presentation_get($sourceId));
        if($withImages) foreach(piece_images($sourceId) as $image) {
            $token=bin2hex(random_bytes(16)); $batch=['token'=>$token,'variants'=>[]];
            ensure_storage();
            if(!mkdir(STORAGE.'/images/'.$token,0770,true)) throw new RuntimeException('No se pudo copiar la imagen.');
            $copies[]=$batch; $pos=count($copies)-1;
            query('INSERT INTO images (piece_id,role,position,alt_text,caption,focal_x,focal_y) VALUES (?,?,?,?,?,?,?)',[$id,$image['role'],$image['position'],$image['alt_text'],$image['caption'],$image['focal_x'],$image['focal_y']]);
            $newImage=(int)db()->lastInsertId();
            foreach(query('SELECT * FROM image_variants WHERE image_id=?',[$image['id']])->fetchAll() as $variant) {
                if($variant['storage_backend']!=='local') throw new InvalidArgumentException('Esta imagen necesita copia desde su almacenamiento externo.');
                $from=image_key($variant['storage_key']); $key=$token.'/'.$variant['variant'].'.jpg';
                $copies[$pos]['variants'][$variant['variant']]=['key'=>$key];
                if(!is_file($from)||!copy($from,image_key($key))) throw new RuntimeException('No se pudo copiar una foto.');
                query('INSERT INTO image_variants (image_id,variant,storage_key,mime_type,width,height,byte_size) VALUES (?,?,?,?,?,?,?)',[$newImage,$variant['variant'],$key,$variant['mime_type'],$variant['width'],$variant['height'],$variant['byte_size']]);
            }
            if($image['is_cover']) query('INSERT INTO piece_cover VALUES (?,?)',[$id,$newImage]);
        }
        db()->commit(); return $id;
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); remove_generated($copies); throw $e; }
}
