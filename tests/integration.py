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
    assert media.headers['Content-Type'] == 'image/jpeg' and media.content[:2] == b'\xff\xd8'
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
assert get('media', id=image_id).headers['Content-Type'] == 'image/jpeg'
r = post('restore&id=' + str(piece_id), {'csrf': csrf})
assert 'Pieza restaurada' in r.text
assert 'SECRETO-UBICACION' in get('edit', id=piece_id).text

# La carga de contenido ajeno a imágenes no escribe una nueva ficha.
new = get('edit')
r = post('edit', {**common, 'csrf': token(new.text), 'title': 'TEST Archivo inválido'},
         files={'photos[]': ('fake.jpg', io.BytesIO(b'<?php echo 1;'), 'image/jpeg')})
assert 'Usá fotos JPG' in r.text
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
r = post('demo', {'csrf': token(r.text), 'action': 'trash'})
assert '20 ejemplos enviados a papelera' in r.text
assert get('list', q='DEMO-').text.count('class="piece-card card"') == 0
assert get('list', trash=1, q='DEMO-').text.count('class="piece-card card"') == 20
assert 'Editar ficha' in get('list').text  # La pieza propia sigue activa.
r = post('demo', {'csrf': token(r.text), 'action': 'install'})
assert 'No se duplicó' in r.text
assert get('list', q='DEMO-').text.count('class="piece-card card"') == 0
r = post('demo', {'csrf': token(r.text), 'action': 'restore'})
assert '20 ejemplos restaurados' in r.text
assert get('list', q='DEMO-').text.count('class="piece-card card"') == 20

r = post('logout', {'csrf': token(get('edit', id=piece_id).text)})
assert 'Ingresá a Pródigo' in r.text
r = post('login', {'csrf': token(r.text), 'email': 'test@example.invalid', 'password': password})
assert 'Piezas' in r.text and 'Ingresá a Pródigo' not in r.text
print('OK: instalación única, CSRF, fichas, campos, privacidad, publicación, JPEG, sesión, concurrencia, papelera, validación, login y 20 ejemplos sin duplicados con papelera/restauración.')
