<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/catalog.php';
require __DIR__.'/app/public_catalog.php';
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)) { http_response_code(405); header('Allow: GET, HEAD'); exit; }
$view=shop_text('vista',30); $slug=shop_text('pieza',180); $piece=null; $error=false; $demo=false; $shopPreview=false; $previewRequested=shop_text('preview',1)==='1';
try {
    if($view==='azar') {
        $random=query('SELECT slug FROM public_pieces ORDER BY RAND() LIMIT 1')->fetchColumn();
        header('Location: '.shop_url($random?['pieza'=>$random]:['vista'=>'catalogo'])); exit;
    }
    if($slug!=='') {
        $shopPreview=$previewRequested && (bool)admin();
        $piece=($previewRequested&&!$shopPreview)?false:query(shop_select($shopPreview).'WHERE p.slug=?',[$slug])->fetch();
        if(!$piece) http_response_code(404);
    }
    $demo=(int)query("SELECT COUNT(*) FROM public_pieces WHERE code LIKE 'DEMO-%'")->fetchColumn()>0;
} catch(Throwable $e) { report_error($e); http_response_code(503); $error=true; }
shop_header($piece?shop_title($piece):($view==='catalogo'?'Catálogo':'Objetos con otra vida'),$demo,$piece?:null);
try {
    if($shopPreview&&$piece) { ?><div class="private-preview">Previsualización privada · <?=h(PUBLICATION[$piece['publication_status']])?> · Cambios guardados <a href="<?=h(url('edit',['id'=>$piece['id']]))?>">Volver a editar ↗</a></div><?php }
    if($error): ?><section class="catalog-empty"><h1>Volvemos en un momento.</h1><p>El catálogo no está disponible temporalmente.</p><a href="catalogo.php">Volver a intentar</a></section><?php
    elseif($slug!==''&&!$piece): ?><section class="catalog-empty"><p class="kicker">Pródigo</p><h1>Esta pieza no está disponible para ver.</h1><a class="text-link" href="<?=h(shop_url(['vista'=>'catalogo']))?>">Volver al catálogo ↗</a></section><?php
    elseif($piece): shop_item($piece);
    elseif($view==='catalogo'): shop_listing();
    else: shop_home();
    endif;
} catch(Throwable $e) { report_error($e); http_response_code(503); ?><div class="catalog-empty"><h2>No pudimos completar esta vista.</h2><a href="catalogo.php">Volver al inicio</a></div><?php }
shop_footer();
