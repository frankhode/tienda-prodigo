<?php
declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD']==='GET' && !isset($_GET['page'])) { header('Location: catalogo.php'); exit; }
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/catalog.php';
require __DIR__ . '/app/views.php';
require __DIR__ . '/app/demo.php';
$page=is_string($_GET['page']??null)?$_GET['page']:'list';
$error=''; $user=null; $setup=false;
try {
    $setup=(int)query('SELECT COUNT(*) FROM admins')->fetchColumn()===0;
    $user=admin();
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH']??0)>0) throw new InvalidArgumentException('La carga supera post_max_size de PHP. Subí menos fotos por vez o aumentá ese límite.');
        check_csrf();
        if ($page==='setup' && $setup) { setup_admin(); flash('Tu cuenta está lista. Ya podés cargar piezas.'); redirect('list'); }
        if ($page==='login' && !$setup) { do_login(); redirect('list'); }
        if (!$user) { http_response_code(403); throw new InvalidArgumentException('Ingresá para continuar.'); }
        if ($page==='logout') { $_SESSION=[]; session_destroy(); redirect('login'); }
        if ($page==='demo') {
            $action=choice('action',['install','trash','restore'],'install');
            if ($action==='install') { $count=install_demo(); flash($count ? "$count piezas de ejemplo agregadas." : 'Los ejemplos ya están cargados. No se duplicó ninguna ficha.'); }
            else { $count=demo_trash($action==='restore'); flash("$count ejemplos " . ($action==='restore'?'restaurados.':'enviados a papelera.')); }
            redirect('demo');
        }
        if($page==='duplicate') {
            $id=duplicate_piece(id_param(),!empty($_POST['with_images']));
            flash('Copia creada como borrador, con código propio. Revisala antes de publicar.');
            redirect('edit',['id'=>$id]);
        }
        if($page==='appearance') {
            $id=id_param();
            if(isset($_POST['enable_templates'])) {
                db()->exec(file_get_contents(__DIR__.'/database/004_presentations.sql'));
                flash('Plantillas habilitadas. Ya podés elegir la presentación de cada pieza.');
            } else { presentation_update($id); flash('Visualización guardada.'); }
            redirect('appearance',['id'=>$id]);
        }
        if ($page==='edit') {
            $id=id_param();
            if (isset($_POST['delete_image'])) {
                delete_image($id,(int)$_POST['delete_image']); flash('Foto eliminada.'); redirect('edit',['id'=>$id]);
            }
            $id=save_piece($id); flash('Ficha guardada.');
            if(!empty($_POST['_wizard'])) {
                $_SESSION['last_categories']=array_map('intval',$_POST['categories']??[]);
                if(($_POST['after_save']??'')==='new') redirect('edit',['repeat'=>1]);
                redirect('list',$_SESSION['list_params']??[]);
            }
            redirect('edit',['id'=>$id]);
        }
        if ($page==='trash'||$page==='restore') { trash_piece(id_param(),$page==='restore'); flash($page==='restore'?'Pieza restaurada.':'Pieza enviada a papelera.'); redirect('list',$_SESSION['list_params']??[]); }
    }
    if (!$user && !in_array($page,['login','setup'],true)) redirect($setup?'setup':'login');
    if ($user && in_array($page,['login','setup'],true)) redirect('list');
    if (!$user) $page=$setup?'setup':'login';
    if ($page==='media') {
        $variant=is_string($_GET['variant']??null)?$_GET['variant']:'thumbnail';
        if (!in_array($variant,['thumbnail','web','zoom'],true)) throw new InvalidArgumentException('Versión de imagen inválida.');
        $image=query('SELECT v.storage_key FROM image_variants v JOIN images i ON i.id=v.image_id JOIN pieces p ON p.id=i.piece_id WHERE v.image_id=? AND v.variant=?',[id_param(),$variant])->fetch();
        if (!$image) { http_response_code(404); exit; }
        $file=image_key($image['storage_key']); if (!is_file($file)) { http_response_code(404); exit; }
        header('Content-Type: image/jpeg'); header('Content-Length: '.filesize($file)); session_write_close(); readfile($file); exit;
    }
} catch (Throwable $e) { $error=report_error($e); }
if($user&&$page==='preview'&&$error==='') { try { $p=get_piece(id_param()); header('Location: catalogo.php?'.http_build_query(['pieza'=>$p['slug'],'preview'=>1])); exit; } catch(Throwable $e) { $error=report_error($e); } }
header_view('Panel',$user);
try {
    if (!$user && in_array($page,['login','setup'],true)) login_view($setup,$error);
    elseif (!$user) { ?><section class="card"><h1>Conectemos la base de datos</h1><div class="notice error"><?=h($error)?></div><p>Iniciá MySQL en XAMPP, verificá que exista la base prodigo e importá el esquema y las categorías. Si cambiaste los datos de conexión, copiá config/example.php como config/local.php y editá esa copia.</p><a class="button" href="index.php">Volver a comprobar</a></section><?php }
    elseif ($page==='demo') demo_view($error);
    elseif ($page==='appearance') appearance_view(id_param(),$error);
    elseif ($page==='edit') edit_view(id_param(),$error);
    elseif ($page==='preview') { ?><div class="notice error"><?=h($error)?></div><?php }
    else list_view($error);
} catch (Throwable $e) { ?><div class="notice error" role="alert"><?=h(report_error($e))?></div><a class="button" href="<?=h(url('list'))?>">Volver al listado</a><?php }
footer_view();
