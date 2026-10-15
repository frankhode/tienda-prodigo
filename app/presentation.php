<?php
declare(strict_types=1);
const PRESENTATION_OPTIONS = [
 'template'=>['panels'=>'Paneles · texto a la izquierda, imagen a la derecha','wallpaper'=>'Wallpaper · imagen de fondo'],
 'title_font'=>['classic'=>'Clásica · Georgia','book'=>'Libro · Palatino','sans'=>'Moderna · Arial','mono'=>'Máquina · Courier'],
 'body_font'=>['sans'=>'Sin serif · Arial','classic'=>'Con serif · Georgia','mono'=>'Monoespaciada · Courier'],
 'title_size'=>['small'=>'Pequeño','medium'=>'Mediano','large'=>'Grande'],
 'body_size'=>['small'=>'Pequeño','medium'=>'Mediano','large'=>'Grande'],
 'title_style'=>['normal'=>'Normal','italic'=>'Cursiva'],
 'text_position'=>['left'=>'Izquierda','center'=>'Centro','right'=>'Derecha'],
 'tone'=>['dark'=>'Fondo oscuro · texto claro','light'=>'Fondo claro · texto oscuro'],
];
function presentation_defaults(): array {
 return ['template'=>'panels','title_font'=>'classic','body_font'=>'sans','title_size'=>'medium','body_size'=>'medium','title_style'=>'normal','text_position'=>'left','tone'=>'dark','veil'=>60,'focal_x'=>50,'focal_y'=>50,'lead'=>''];
}
function presentation_ready(): bool {
 return (bool)query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='piece_presentations'")->fetchColumn();
}
function presentation_get(int $id): array {
 $defaults=presentation_defaults();
 if(!$id||!presentation_ready()) return $defaults;
 $row=query('SELECT * FROM piece_presentations WHERE piece_id=?',[$id])->fetch();
 if(!$row) return $defaults;
 foreach(PRESENTATION_OPTIONS as $key=>$options) if(!isset($options[$row[$key]??''])) $row[$key]=$defaults[$key];
 foreach(['focal_x','focal_y'] as $key) $row[$key]=max(0,min(100,(int)$row[$key]));
 if(!in_array((int)$row['veil'],[20,40,60,80],true)) $row['veil']=60;
 return array_replace($defaults,$row);
}
function presentation_input(): array {
 $settings=[]; $defaults=presentation_defaults();
 foreach(PRESENTATION_OPTIONS as $key=>$options) $settings[$key]=choice('display_'.$key,array_keys($options),$defaults[$key]);
 foreach(['veil','focal_x','focal_y'] as $key) {
  $value=optional_integer('display_'.$key,0,100)??$defaults[$key];
  if(($key==='veil'&&!in_array($value,[20,40,60,80],true))||($key!=='veil'&&$value%10!==0)) throw new InvalidArgumentException('Elegí uno de los valores de encuadre y contraste indicados.');
  $settings[$key]=$value;
 }
 $settings['lead']=text_input('display_lead',600);
 return $settings;
}
function presentation_save(int $id,array $settings): void {
 $columns=array_keys(presentation_defaults());
 query('INSERT INTO piece_presentations (piece_id,'.implode(',',array_map(fn($key)=>'`'.$key.'`',$columns)).') VALUES ('.implode(',',array_fill(0,count($columns)+1,'?')).') ON DUPLICATE KEY UPDATE '.implode(',',array_map(fn($key)=>'`'.$key.'`=VALUES(`'.$key.'`)',$columns)),[$id,...array_map(fn($key)=>$settings[$key],$columns)]);
}
function presentation_classes(array $s): string {
 return 'template-'.$s['template'].' title-font-'.$s['title_font'].' body-font-'.$s['body_font'].' title-size-'.$s['title_size'].' body-size-'.$s['body_size'].' title-style-'.$s['title_style'].' text-'.$s['text_position'].' tone-'.$s['tone'].' veil-'.(int)$s['veil'].' focus-x-'.(int)$s['focal_x'].' focus-y-'.(int)$s['focal_y'];
}
function presentation_editor(int $id): void {
 ?><section class="card"><h2>Plantilla de visualización</h2><?php if(!presentation_ready()): ?><p>Habilitá las plantillas para elegir la presentación de cada pieza. Se agrega una tabla; tus fichas e imágenes se conservan.</p><button type="submit" name="enable_templates" value="1" formnovalidate>Habilitar plantillas</button><p class="muted">La configuración visual se guarda por separado de los datos de la ficha.</p><?php else: $settings=presentation_get($id); ?><p class="muted">La misma ficha, dos formas de presentarla. Los cambios se aplican al guardar.</p><?php if($id): $piece=get_piece($id); ?><p><a class="button" target="_blank" rel="noopener" href="<?=h('catalogo.php?'.http_build_query(['pieza'=>$piece['slug'],'preview'=>1,'plantilla'=>'panels']))?>">Probar paneles ↗</a> <a class="button" target="_blank" rel="noopener" href="<?=h('catalogo.php?'.http_build_query(['pieza'=>$piece['slug'],'preview'=>1,'plantilla'=>'wallpaper']))?>">Probar wallpaper ↗</a></p><p class="muted">Estas pruebas usan los datos y estilos guardados y no cambian la plantilla elegida.</p><?php endif ?><div class="field-grid"><?php
 $labels=['template'=>'Plantilla','title_font'=>'Tipografía del título','body_font'=>'Tipografía del texto','title_size'=>'Tamaño del título','body_size'=>'Tamaño del texto','title_style'=>'Estilo del título','text_position'=>'Posición del texto en wallpaper','tone'=>'Contraste en wallpaper'];
 foreach(PRESENTATION_OPTIONS as $key=>$options) select_view('display_'.$key,$labels[$key],$options,['display_'.$key=>$settings[$key]],presentation_defaults()[$key]);
 ?></div><div class="field-grid"><label>Intensidad del velo<select name="display_veil"><?php foreach([20,40,60,80] as $n): ?><option value="<?=$n?>" <?=(int)($_POST['display_veil']??$settings['veil'])===$n?'selected':''?>><?=$n?> %</option><?php endforeach ?></select><small>Más intensidad facilita la lectura sobre la imagen.</small></label><label>Encuadre horizontal del fondo<select name="display_focal_x"><?php for($n=0;$n<=100;$n+=10): ?><option value="<?=$n?>" <?=(int)($_POST['display_focal_x']??$settings['focal_x'])===$n?'selected':''?>><?=$n?> %<?=$n===0?' · izquierda':($n===100?' · derecha':($n===50?' · centro':''))?></option><?php endfor ?></select></label><label>Encuadre vertical del fondo<select name="display_focal_y"><?php for($n=0;$n<=100;$n+=10): ?><option value="<?=$n?>" <?=(int)($_POST['display_focal_y']??$settings['focal_y'])===$n?'selected':''?>><?=$n?> %<?=$n===0?' · arriba':($n===100?' · abajo':($n===50?' · centro':''))?></option><?php endfor ?></select></label></div><label>Texto breve sobre la imagen<textarea name="display_lead" rows="3" maxlength="600"><?=h($_POST['display_lead']??$settings['lead'])?></textarea><small>Opcional. La descripción completa sigue en la ficha de detalle.</small></label><p class="muted">Wallpaper utiliza la portada. En celular, el texto se apila sobre un velo más intenso y los detalles quedan debajo. Las tipografías disponibles son familias del sistema, sin descargas externas.</p><?php endif ?></section><?php
}

function presentation_version(int $id): string {
    return hash('sha256',json_encode(presentation_get($id),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
}
function presentation_update(int $id): void {
    if(!$id) throw new InvalidArgumentException('Elegí una pieza guardada.');
    if(!presentation_ready()) throw new InvalidArgumentException('Primero habilitá las plantillas.');
    $settings=presentation_input();
    db()->beginTransaction();
    try {
        query('SELECT id FROM pieces WHERE id=? FOR UPDATE',[$id]);
        $piece=get_piece($id);
        if($piece['deleted_at']) throw new InvalidArgumentException('Restaurá la pieza antes de editar su visualización.');
        if(presentation_version($id)!==text_input('version',64)) throw new InvalidArgumentException('La visualización cambió en otra pestaña. Recargá antes de guardar.');
        presentation_save($id,$settings);
        db()->commit();
    } catch(Throwable $e) { if(db()->inTransaction()) db()->rollBack(); throw $e; }
}
function appearance_view(int $id,string $error): void {
    ?><div class="page-heading"><div><p class="eyebrow">Presentación de las piezas</p><h1>Visualización</h1><p class="muted">Plantilla, tipografías y encuadre. Se guardan de manera independiente de la ficha.</p></div><a class="button" href="<?=h(url('list'))?>">Volver a piezas</a></div><?php
    if($error): ?><div class="notice error" role="alert"><?=h($error)?></div><?php endif;
    $rows=query('SELECT id,code,title FROM pieces WHERE deleted_at IS NULL ORDER BY title,id')->fetchAll();
    ?><form class="filters card" method="get"><input type="hidden" name="page" value="appearance"><label>Elegir pieza<select name="id"><option value="">Seleccioná una pieza</option><?php foreach($rows as $p): ?><option value="<?=$p['id']?>" <?=(int)$p['id']===$id?'selected':''?>><?=h($p['title'].' · '.$p['code'])?></option><?php endforeach ?></select></label><button type="submit">Abrir visualización</button></form><?php
    if(!$id) {
        if(!presentation_ready()): ?><form method="post" action="<?=h(url('appearance'))?>"><?=csrf()?><?php presentation_editor(0); ?></form><?php endif;
        return;
    }
    $piece=get_piece($id);
    if($piece['deleted_at']) throw new InvalidArgumentException('Restaurá primero la pieza.');
    ?><div class="page-heading"><h2><?=h($piece['title'])?></h2><a class="button" href="<?=h(url('preview',['id'=>$id]))?>" target="_blank" rel="noopener">Previsualizar ↗</a></div><form class="appearance-form" method="post" action="<?=h(url('appearance',['id'=>$id]))?>"><?=csrf()?><input type="hidden" name="version" value="<?=h($_POST['version']??presentation_version($id))?>"><?php presentation_editor($id); if(presentation_ready()): ?><div class="savebar"><span>Se aplicará sólo la presentación visual.</span><button class="primary" type="submit">Guardar visualización</button></div><?php endif ?></form><?php
}
