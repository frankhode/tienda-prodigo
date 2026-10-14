<?php
declare(strict_types=1);
require_once __DIR__.'/demo_art.php';
const DEMO_MARKER = 'prodigo:demo:v1';
function demo_data(): array {
    return json_decode(file_get_contents(__DIR__.'/demo-data.json'),true,512,JSON_THROW_ON_ERROR);
}
function demo_ids(): array {
    $codes=array_column(demo_data(),'code');
    return array_map('intval',array_column(query('SELECT p.id FROM pieces p JOIN piece_private v ON v.piece_id=p.id WHERE v.acquisition_source=? AND p.code IN ('.implode(',',array_fill(0,count($codes),'?')).')',[DEMO_MARKER,...$codes])->fetchAll(),'id'));
}
function install_demo(): int {
    if(!function_exists('imagecreatetruecolor')) throw new InvalidArgumentException('Activá GD en PHP y reiniciá Apache para generar las ilustraciones de ejemplo.');
    if((int)query("SELECT GET_LOCK('prodigo_demo_v1',5)")->fetchColumn()!==1) throw new InvalidArgumentException('Los ejemplos se están cargando en otra pestaña. Esperá y volvé a intentar.');
    $generated=[]; $count=0;
    try {
        db()->beginTransaction();
        $categories=[];
        foreach(query('SELECT id,slug FROM categories')->fetchAll() as $cat) $categories[$cat['slug']]=(int)$cat['id'];
        foreach(demo_data() as $item) {
            $cat=$categories[$item['category']]??null;
            if(!$cat) throw new InvalidArgumentException('Falta la categoría '.$item['category'].'. Los ejemplos necesitan las diez categorías iniciales.');
            // Los códigos estables impiden duplicar incluso si la ficha se editó o está en papelera.
            if(query('SELECT id FROM pieces WHERE code=?',[$item['code']])->fetch()) continue;
            query('INSERT INTO pieces (code,slug,title,unit_type,component_count,publication_status,availability,description,dating_label,year_from,year_to,dating_certainty,dimensions_label,condition_description,price_mode,published_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[
                $item['code'],strtolower($item['code']),$item['title'],$item['unit_type'],$item['component_count'],$item['publication_status'],$item['availability'],$item['description'],'Hacia '.$item['year'].' (ejemplo)',$item['year']-5,$item['year']+5,'estimated',$item['dimensions'],
                'Ejemplo ficticio: desgaste superficial y señales de uso. Esta descripción sólo sirve para visualizar el campo de conservación.','undecided',$item['publication_status']==='published'?date('Y-m-d H:i:s'):null,
            ]);
            $id=(int)db()->lastInsertId();
            query('INSERT INTO piece_categories VALUES (?,?)',[$id,$cat]);
            $defs=array_column(query('SELECT field_key FROM category_fields WHERE category_id=?',[$cat])->fetchAll(),'field_key');
            foreach($item['fields'] as $key=>$value) {
                if(!in_array($key,$defs,true)) throw new InvalidArgumentException('Falta el campo '.$key.' en la categoría '.$item['category'].'.');
                query('INSERT INTO piece_field_values VALUES (?,?,?,?)',[$id,$cat,$key,$value]);
            }
            query('INSERT INTO piece_private (piece_id,physical_location,acquisition_source,internal_notes) VALUES (?,?,?,?)',[$id,'Ubicación de ejemplo · Estante '.(int)ceil($count/4+1),DEMO_MARKER,'Ficha de demostración. Podés editarla para explorar el panel; enviá los ejemplos a papelera cuando empieces con piezas reales.']);
            for($n=0;$n<2;$n++) {
                $image=demo_image($item['category'],$item['variant'],$n===1); $generated[]=$image;
                query('INSERT INTO images (piece_id,role,position,alt_text,caption) VALUES (?,?,?,?,?)',[$id,$n===0?'presentation':'detail',$n+1,($n===0?'Ilustración de ejemplo: ':'Detalle ilustrativo de ejemplo: ').$item['title'],'Ilustración esquemática de demostración. No es una fotografía de un objeto real.']);
                $imageId=(int)db()->lastInsertId();
                foreach($image['variants'] as $name=>$v) query('INSERT INTO image_variants (image_id,variant,storage_key,mime_type,width,height,byte_size) VALUES (?,?,?,?,?,?,?)',[$imageId,$name,$v['key'],'image/jpeg',$v['width'],$v['height'],$v['bytes']]);
                if($n===0) query('INSERT INTO piece_cover VALUES (?,?)',[$id,$imageId]);
            }
            $count++;
        }
        db()->commit(); return $count;
    } catch(Throwable $e) {
        if(db()->inTransaction()) db()->rollBack();
        remove_generated($generated); throw $e;
    } finally { query("SELECT RELEASE_LOCK('prodigo_demo_v1')"); }
}
function demo_trash(bool $restore): int {
    $ids=demo_ids(); if(!$ids) return 0;
    db()->beginTransaction();
    try {
        // Bloqueamos en el mismo orden que las operaciones de fichas.
        foreach($ids as $id) {
            query('SELECT id FROM pieces WHERE id=? FOR UPDATE',[$id]);
            if(!$restore && query("SELECT id FROM transactions WHERE piece_id=? AND status='reserved'",[$id])->fetch()) throw new InvalidArgumentException('Un ejemplo tiene una reserva registrada. Cancelala antes de enviarlo a papelera.');
        }
        $stmt=query('UPDATE pieces SET deleted_at='.($restore?'NULL':'CURRENT_TIMESTAMP').' WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).') AND deleted_at IS '.($restore?'NOT NULL':'NULL'),$ids);
        $count=$stmt->rowCount(); db()->commit(); return $count;
    } catch(Throwable $e) { db()->rollBack(); throw $e; }
}
function demo_view(string $error): void {
    $ids=demo_ids(); $active=0;
    if($ids) $active=(int)query('SELECT COUNT(*) FROM pieces WHERE deleted_at IS NULL AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids)->fetchColumn();
    ?><div class="page-heading"><div><p class="eyebrow">Explorar el panel</p><h1>Piezas de ejemplo</h1><p class="muted">Dos fichas por cada una de las diez categorías.</p></div><a class="button" href="<?=h(url('list',['q'=>'DEMO-']))?>">Ver ejemplos</a></div><?php if($error): ?><div class="notice error" role="alert"><?=h($error)?></div><?php endif ?>
    <section class="card demo-intro"><h2>Un catálogo para empezar a mirar</h2><p>20 objetos y conjuntos ficticios, con una ilustración general y un detalle por ficha. Incluyen descripciones, medidas, datación aproximada, campos por categoría y distintos estados.</p><p><strong>No son piezas reales ni imágenes de referencia histórica.</strong> Sus títulos y textos los identifican como demostración. Los precios siguen pendientes.</p><p>Tenés <strong><?=$active?></strong> ejemplos activos y <strong><?=count($ids)-$active?></strong> en papelera.</p><form method="post" action="<?=h(url('demo'))?>"><?=csrf()?><button class="primary" name="action" value="install">Cargar los 20 ejemplos</button></form><p class="muted">Se agregan sólo los que faltan. Repetir la carga conserva las fichas existentes, sus cambios y su estado de papelera. Las piezas reales no se modifican.</p></section>
    <div class="demo-categories"><?php foreach(query('SELECT name,slug FROM categories ORDER BY position,name')->fetchAll() as $cat): ?><span class="badge"><?=h($cat['name'])?></span><?php endforeach ?></div>
    <?php if($ids): ?><section class="card demo-intro"><h2>Cuando quieras seguir con tus piezas</h2><p>Podés enviar todos los ejemplos a papelera y restaurarlos después. Sus imágenes se conservan. La acción sólo alcanza las fichas creadas por este cargador.</p><div class="demo-actions"><form method="post" action="<?=h(url('demo'))?>"><?=csrf()?><button class="danger" name="action" value="trash" data-confirm="¿Enviar las piezas de demostración a papelera? Se conservarán para poder restaurarlas.">Enviar ejemplos a papelera</button></form><form method="post" action="<?=h(url('demo'))?>"><?=csrf()?><button name="action" value="restore">Restaurar ejemplos</button></form></div></section><?php endif ?><?php
}
