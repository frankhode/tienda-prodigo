"""Prueba HTTP sobre una instalación VACÍA de prueba. Nunca usar sobre datos reales.
Uso: python tests/integration.py http://127.0.0.1:8088 --allow-write
Requiere requests. Crea una cuenta y piezas de prueba, que quedan en esa base.
"""
import io
import re
import secrets
import sys
from urllib.parse import urlparse
import requests

if len(sys.argv) != 3 or sys.argv[2] != '--allow-write':
    raise SystemExit(__doc__)
base = sys.argv[1].rstrip('/') + '/'
if urlparse(base).hostname not in ('localhost', '127.0.0.1', '::1'):
    raise SystemExit('Sólo se admite un servidor local de prueba.')
s = requests.Session()
def get(page='list', **params):
    r = s.get(base + 'index.php', params={'page': page, **params}, timeout=30)
    r.raise_for_status()
    return r

def token(html, name='csrf'):
    found = re.search(r'name="' + name + r'" value="([^"]*)"', html)
    assert found, (name, html[:200])
    return found.group(1)

def post(page, data, **kwargs):
    return s.post(base + 'index.php?page=' + page, data=data, timeout=30, **kwargs)

page = get('setup')
assert 'Creá tu cuenta' in page.text, 'Se requiere una base de prueba sin administradores.'
password = secrets.token_urlsafe(24)
r = post('setup', {'csrf': token(page.text), 'email': 'test@example.invalid', 'password': password})
assert 'Tu cuenta está lista' in r.text
r = post('setup', {'csrf': token(r.text), 'email': 'other@example.invalid', 'password': password})
assert 'Creá tu cuenta' not in r.text, 'Se reabrió la instalación.'

# Un POST sin CSRF no debe crear datos.
r = post('edit', {'title': 'No debe existir', 'categories[]': '3'})
assert r.status_code == 403
assert 'No debe existir' not in get().text

page = get('edit')
common = {'csrf': token(page.text), 'title': 'TEST Radio y objetos', 'categories[]': '3',
          'unit_type': 'set', 'component_count': '2', 'publication_status': 'draft',
          'availability': 'available', 'dating_certainty': 'estimated',
          'dating_label': 'Hacia 1930', 'year_from': '1925', 'year_to': '1935',
          'fields[3][marca]': 'Marca de prueba', 'physical_location': 'SECRETO-UBICACION',
          'internal_notes': 'SECRETO-NOTAS'}
r = post('edit', common)
assert 'Ficha guardada' in r.text
piece_id = int(re.search(r'page=edit[^\"]*?id=(\d+)', r.text).group(1))
edit_url = 'edit&id=' + str(piece_id)
version = token(r.text, 'version')
preview = get('preview', id=piece_id).text
assert 'Marca de prueba' in preview and '2 componentes' in preview
assert 'SECRETO-' not in preview

# Publicar sin portada rechaza y deja la ficha como borrador.
r = post(edit_url, {**common, 'version': version, 'publication_status': 'published'})
assert 'Para publicar' in r.text
assert 'Borrador' in get('preview', id=piece_id).text

# PNG mínimo válido: debe procesarse aunque el nombre diga .php.
import struct, zlib
def chunk(kind, data):
    return struct.pack('!I', len(data)) + kind + data + struct.pack('!I', zlib.crc32(kind + data) & 0xffffffff)
png = b'\x89PNG\r\n\x1a\n' + chunk(b'IHDR', struct.pack('!2I5B', 1, 1, 8, 2, 0, 0, 0)) + chunk(b'IDAT', zlib.compress(b'\x00\xff\x00\x00')) + chunk(b'IEND', b'')
r = post(edit_url, {**common, 'version': version, 'publication_status': 'published'},
         files={'photos[]': ('test.php', io.BytesIO(png), 'image/png')})
assert 'Ficha guardada' in r.text, re.findall(r'<div class="notice error"[^>]*>(.*?)</div>', r.text, re.S)
image_id = int(re.search(r'name="cover" value="(\d+)"', r.text).group(1))
version = token(r.text, 'version')
for variant in ('thumbnail', 'web', 'zoom'):
    media = get('media', id=image_id, variant=variant)
    assert media.headers['Content-Type'] == 'image/webp' and media.content[:4] == b'RIFF' and media.content[8:12] == b'WEBP'
anon = requests.get(base + 'index.php', params={'page': 'media', 'id': image_id}, timeout=30)
assert 'Ingresá a Pródigo' in anon.text and 'image/jpeg' not in anon.headers.get('Content-Type', '')

# Texto escapado y detección de edición en otra pestaña.
r = post(edit_url, {**common, 'title': '<script>prueba</script>', 'version': version, 'cover': image_id})
assert 'Ficha guardada' in r.text
assert '<script>prueba</script>' not in get('preview', id=piece_id).text
r = post(edit_url, {**common, 'version': version, 'cover': image_id})
assert 'otra pestaña' in r.text

# Papelera y restauración sin perder imágenes.
csrf = token(get('edit', id=piece_id).text)
r = post('trash&id=' + str(piece_id), {'csrf': csrf})
assert 'Pieza enviada a papelera' in r.text
assert 'PR-' in get('list', trash=1).text
assert get('media', id=image_id).headers['Content-Type'] == 'image/webp'
r = post('restore&id=' + str(piece_id), {'csrf': csrf})
assert 'Pieza restaurada' in r.text
assert 'SECRETO-UBICACION' in get('edit', id=piece_id).text

# La carga de contenido ajeno a imágenes no escribe una nueva ficha.
new = get('edit')
r = post('edit', {**common, 'csrf': token(new.text), 'title': 'TEST Archivo inválido'},
         files={'photos[]': ('fake.jpg', io.BytesIO(b'<?php echo 1;'), 'image/jpeg')})
assert 'Usá JPG' in r.text
assert 'TEST Archivo inválido' not in get().text

# Los ejemplos se cargan una sola vez, generan fotos y no afectan las piezas propias.
r = post('demo', {'csrf': token(get('demo').text), 'action': 'install'})
assert '20 piezas de ejemplo agregadas' in r.text, re.findall(r'<div class="notice error"[^>]*>(.*?)</div>', r.text, re.S)
examples = get('list', q='DEMO-').text
assert examples.count('class="piece-card card"') == 20
for cat in range(1, 11):
    assert get('list', q='DEMO-', category=cat).text.count('class="piece-card card"') == 2
assert 'DEMO-01-01' in examples and 'DEMO-10-02' in examples
sample_images = re.findall(r'page=media[^"\s]*?id=(\d+)', examples)
assert len(sample_images) == 20
for image in sample_images:
    media = get('media', id=image, variant='web')
    assert media.content[:2] == b'\xff\xd8'
r = post('demo', {'csrf': token(r.text), 'action': 'install'})
assert 'No se duplicó' in r.text
assert get('list', q='DEMO-').text.count('class="piece-card card"') == 20
# Frente público sin sesión: portada, catálogo, ficha e imágenes sólo publicadas.
visitor = requests.Session()
visitor.trust_env = False
def public(path='catalogo.php', **params):
    return visitor.get(base + path, params=params, timeout=30)
assert public('index.php').url.endswith('catalogo.php')
home = public()
assert home.status_code == 200 and 'minimal-home' in home.text and 'Sorprendeme' in home.text
assert home.text.count('class="material-tile ') == 10
assert 'hero-pieces' not in home.text
catalog = public(vista='catalogo')
assert catalog.status_code == 200 and catalog.text.count('class="object-card"') == 18
assert '[DEMO]' not in catalog.text  # Los títulos se limpian en la vista, la advertencia permanece.
assert 'Muestra de demostración' in catalog.text
assert public(vista='catalogo', categoria='radios').text.count('class="object-card"') == 2
assert public(vista='catalogo', categoria='dibujos').text.count('class="object-card"') == 1
assert public(vista='catalogo', q='Marca de ejemplo').text.count('class="object-card"') > 0
assert public(vista='catalogo', estado='sold').text.count('class="object-card"') == 2
assert public(vista='catalogo', orden='antiguas').status_code == 200
assert public(vista='catalogo', q='<script>alert(1)</script>').status_code == 200
assert '<script>alert(1)</script>' not in public(vista='catalogo', q='<script>alert(1)</script>').text
assert public(pieza='demo-05-02').status_code == 404
assert public(pieza='demo-10-02').status_code == 404
item = public(pieza='demo-03-01')
assert item.status_code == 200 and 'zoom-dialog' in item.text and 'Otras piezas' in item.text
assert 'internal_notes' not in item.text and 'physical_location' not in item.text and 'SECRETO-' not in item.text
assert 'Consultar por esta pieza' not in item.text  # Sin canal configurado no hay botón sin destino.
public_image_id = int(re.search(r'imagen.php\?id=(\d+)', item.text).group(1))
assert public('imagen.php', id=public_image_id, variant='web').headers['Content-Type'] == 'image/jpeg'
# Aun con sesión de administrador, las fichas en borrador no se vuelven públicas.
assert s.get(base+'catalogo.php', params={'pieza':'demo-05-02'}, timeout=30).status_code == 404
chunks = re.findall(r'<article class="piece-card card">(.*?)</article>', examples, re.S)
draft = next(chunk for chunk in chunks if 'DEMO-05-02' in chunk)
draft_image_id = int(re.search(r'page=media[^"\s]*?id=(\d+)', draft).group(1))
assert public('imagen.php', id=draft_image_id).status_code == 404
assert public('imagen.php', id=public_image_id, variant='../../config/local.php').status_code == 404
assert public(vista='azar').status_code == 200
assert 'pieza=' in public(vista='azar').url
private = public('index.php', page='list')
assert 'Ingresá a Pródigo' in private.text

# Activación no destructiva y plantillas por pieza; vista previa de borradores privada.
editor = get('appearance')
r = post('appearance', {'csrf': token(editor.text), 'enable_templates': '1'})
assert 'Plantillas habilitadas' in r.text
assert 'Visualización' in r.text
sample_chunk = next(chunk for chunk in chunks if 'DEMO-03-01' in chunk)
sample_piece_id = int(re.search(r'page=edit[^"\s]*?id=(\d+)', sample_chunk).group(1))
data_editor = get('edit', id=sample_piece_id)
assert 'name="display_template"' not in data_editor.text
assert 'data-step="presentation"' not in data_editor.text
data_version = token(data_editor.text, 'version')
original_public = public(pieza='demo-03-01').text
editor = get('appearance', id=sample_piece_id)
assert 'name="display_template"' in editor.text
settings = {'display_template': 'wallpaper', 'display_title_font': 'mono',
            'display_body_font': 'classic', 'display_title_size': 'large',
            'display_body_size': 'large', 'display_title_style': 'italic',
            'display_text_position': 'right', 'display_tone': 'light',
            'display_veil': '80', 'display_focal_x': '80', 'display_focal_y': '20',
            'display_lead': 'Una radio para mirar de cerca. <script>no</script>'}
r = post('appearance&id='+str(sample_piece_id), {**settings,
         'csrf': token(editor.text), 'version': token(editor.text, 'version')})
assert 'Visualización guardada' in r.text, re.findall(r'<div class="notice error"[^>]*>(.*?)</div>', r.text, re.S)
wallpaper = public(pieza='demo-03-01')
assert wallpaper.status_code == 200 and 'wallpaper-hero' in wallpaper.text
assert re.search(r'<h1>(.*?)</h1>', original_public, re.S).group(1) == re.search(r'<h1>(.*?)</h1>', wallpaper.text, re.S).group(1)
for expected in ['title-font-mono', 'body-font-classic', 'text-right', 'tone-light', 'veil-80', 'focus-x-80', 'focus-y-20']:
    assert expected in wallpaper.text
assert '<script>no</script>' not in wallpaper.text and '&lt;script&gt;no&lt;/script&gt;' in wallpaper.text
assert 'La pieza en detalle' in wallpaper.text
assert wallpaper.text.count('id="zoom-dialog"') == 1
assert wallpaper.text.count('<h1>') == 1
saved_version = token(r.text, 'version')
r = post('appearance&id='+str(sample_piece_id), {**settings,
         'csrf': token(r.text), 'version': saved_version, 'display_title_font': 'bad; color:red'})
assert 'Opción de display_title_font incorrecta' in r.text
assert 'title-font-mono' in public(pieza='demo-03-01').text
# Guardar datos con la versión anterior al cambio visual conserva la presentación.
assert token(get('edit', id=sample_piece_id).text, 'version') == data_version
r = post('edit&id='+str(sample_piece_id), {**common, 'csrf':token(data_editor.text),
         'version':data_version, 'publication_status':'published', 'title':'Radio de demostración',
         'display_template':'panels'})
assert 'Ficha guardada' in r.text
assert 'wallpaper-hero' in public(pieza='demo-03-01').text

# El borrador se puede previsualizar con imágenes sin habilitar acceso anónimo.
draft_piece_id = int(re.search(r'page=edit[^"\s]*?id=(\d+)', draft).group(1))
private_preview = get('preview', id=draft_piece_id)
assert 'Previsualización privada' in private_preview.text and 'Borrador' in private_preview.text
assert 'preview=1' in private_preview.url
assert s.get(base+'imagen.php', params={'id':draft_image_id,'preview':'1'},timeout=30).status_code == 200
assert public(pieza='demo-05-02', preview='1').status_code == 404
assert public('imagen.php', id=draft_image_id, preview='1').status_code == 404
# Paneles conservan ajustes tipográficos; ordenar texto antes de la galería en escritorio.
editor = get('appearance', id=sample_piece_id)
r = post('appearance&id='+str(sample_piece_id), {**settings, 'display_template':'panels',
         'csrf':token(editor.text),'version':token(editor.text,'version')})
assert 'Visualización guardada' in r.text
assert 'Radio de demostración' in public(pieza='demo-03-01').text
panels = public(pieza='demo-03-01').text
assert 'template-panels' in panels and 'wallpaper-hero' not in panels
assert panels.index('<article class="item-info">') < panels.index('<div class="item-gallery">')
trial = s.get(base+'catalogo.php', params={'pieza':'demo-03-01','preview':'1','plantilla':'wallpaper'},timeout=30)
assert 'wallpaper-hero' in trial.text
assert 'template-panels' in public(pieza='demo-03-01').text
assert 'wallpaper-hero' not in public(pieza='demo-03-01',plantilla='wallpaper').text

r = post('demo', {'csrf': token(r.text), 'action': 'trash'})
assert '20 ejemplos enviados a papelera' in r.text
assert public(vista='catalogo').text.count('class="object-card"') == 0
assert public('imagen.php', id=public_image_id).status_code == 404
assert public(pieza='demo-03-01').status_code == 404
assert get('list', q='DEMO-').text.count('class="piece-card card"') == 0
assert get('list', trash=1, q='DEMO-').text.count('class="piece-card card"') == 20
assert 'Editar ficha' in get('list').text  # La pieza propia sigue activa.
r = post('demo', {'csrf': token(r.text), 'action': 'install'})
assert 'No se duplicó' in r.text
assert get('list', q='DEMO-').text.count('class="piece-card card"') == 0
r = post('demo', {'csrf': token(r.text), 'action': 'restore'})
assert '20 ejemplos restaurados' in r.text
assert public(vista='catalogo').text.count('class="object-card"') == 18
assert get('list', q='DEMO-').text.count('class="piece-card card"') == 20

# Duplicación: códigos nuevos, borrador y fotos independientes del origen.
from urllib.parse import parse_qs, urlparse
r = post('duplicate&id='+str(piece_id), {'csrf':token(get('edit',id=piece_id).text),'with_images':'1'})
assert 'Copia creada como borrador' in r.text
copy_id = int(parse_qs(urlparse(r.url).query)['id'][0])
assert copy_id != piece_id
assert 'value="draft" selected' in r.text and 'value="not_for_sale" selected' in r.text
assert 'Marca de prueba' in r.text and 'SECRETO-UBICACION' in r.text
copy_image_id = int(re.search(r'name="cover" value="(\d+)"',r.text).group(1))
assert copy_image_id != image_id
# Quitar la imagen al guardar el origen no rompe su copia.
source_editor = get('edit',id=piece_id)
r = post('edit&id='+str(piece_id), {**common,'csrf':token(source_editor.text),
         'version':token(source_editor.text,'version'),'remove_images[]':str(image_id)})
assert 'Ficha guardada' in r.text
assert get('media',id=copy_image_id).headers['Content-Type']=='image/webp'
assert s.get(base+'index.php',params={'page':'media','id':image_id},timeout=30).status_code==404
r = post('duplicate&id='+str(copy_id), {'csrf':token(get('edit',id=copy_id).text)})
assert 'Copia creada como borrador' in r.text
empty_copy_id=int(parse_qs(urlparse(r.url).query)['id'][0])
assert 'name="cover"' not in r.text
# El formulario de ABM tiene todos los campos, incluso los que luego se ocultan en JS.
assert 'editor-shell' in r.text and 'data-step="identity"' in r.text
assert 'data-step="photos"' in r.text and 'data-fields-category="3"' in r.text
r = post('edit', {**common,'csrf':token(r.text),'title':'TEST Carga repetida',
                 '_wizard':'1','after_save':'new'})
assert 'repeat=1' in r.url
assert re.search(r'name="categories\[\]" value="3" checked',r.text)
r = post('edit', {**common,'csrf':token(r.text),'title':'TEST Carga final',
                 '_wizard':'1','after_save':'list'})
assert 'page=list' in r.url and 'Ficha guardada' in r.text
# Una falla al publicar conserva las fotos marcadas para quitar.
copy_editor=get('edit',id=copy_id)
r = post('edit&id='+str(copy_id), {**common,'csrf':token(copy_editor.text),
         'version':token(copy_editor.text,'version'),'publication_status':'published',
         'remove_images[]':str(copy_image_id)})
assert 'Para publicar' in r.text
assert get('media',id=copy_image_id).headers['Content-Type']=='image/webp'
# Ficheros para la prueba DOM del asistente, sobre HTML real generado por PHP.
import os
if os.environ.get('PRODIGO_DOM_FIXTURE_DIR'):
    from pathlib import Path
    fixture=Path(os.environ['PRODIGO_DOM_FIXTURE_DIR'])
    fixture.mkdir(parents=True,exist_ok=True)
    (fixture/'new.html').write_text(get('edit').text)
    (fixture/'edit.html').write_text(get('edit',id=copy_id).text)

r = post('logout', {'csrf': token(get('edit', id=piece_id).text)})
assert 'Ingresá a Pródigo' in r.text
r = post('login', {'csrf': token(r.text), 'email': 'test@example.invalid', 'password': password})
assert 'Piezas' in r.text and 'Ingresá a Pródigo' not in r.text
print('OK: instalación única, CSRF, fichas, campos, privacidad, publicación, JPEG, sesión, concurrencia, papelera, validación, login, ejemplos y catálogo, plantillas, duplicación independiente y ABM con privacidad de fichas e imágenes.')
