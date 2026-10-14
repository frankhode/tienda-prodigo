# Primera instalación en XAMPP

La base `prodigo` ya creada sirve para este panel. **No vuelvas a importar el esquema ni las categorías si ya lo hiciste.** No se necesitan tablas nuevas para esta versión.

1. Descargar el ZIP del repositorio (Code → Download ZIP) y descomprimir.
2. Copiar su contenido a `C:\xampp\htdocs\tienda-prodigo` (o la carpeta htdocs de tu XAMPP). `index.php` debe quedar directamente dentro de esa carpeta.
3. Iniciar Apache y MySQL desde XAMPP.
4. Abrir `http://localhost/tienda-prodigo/`.
5. Entrar a **Gestión privada** al pie del catálogo y crear la cuenta inicial con correo y contraseña de al menos 12 caracteres.

El panel se conecta a `127.0.0.1:3306`, base `prodigo`, usuario `root`, contraseña vacía: valores habituales de XAMPP local. Si tu instalación es diferente, copiar `config/example.php` como `config/local.php` y modificar esa copia. No subir `local.php` a GitHub.

## Primera pieza

En «Nueva pieza», escribir un título, elegir categoría y subir fotos. Guardar como borrador. Los campos de la categoría aparecen al seleccionarla. La primera foto se usa de portada; después se puede elegir otra, editar textos y ordenar arrastrando, con las flechas o por número.

El panel permite título provisional «Sin título» y datos incompletos. Para publicar necesita una categoría y una portada procesada. «Publicado» todavía significa estado guardado: las piezas publicadas ya aparecen en el catálogo de visitantes. La previsualización abre una ficha privada con lo que efectivamente se guardó.

Disponibilidad inicial: «Fuera de venta». Cambiar a «Disponible» cuando corresponda. Los estados «Reservado» y «Vendido» pueden registrarse manualmente en esta etapa; el registro de operaciones comerciales se implementará después.

La papelera conserva la ficha y fotos y permite restaurarla. No es necesario editar directamente las tablas para gestionar piezas.

## Visualización V1

Abrir `http://localhost/tienda-prodigo/`: ahora lleva al catálogo de visitantes. El panel sigue en `http://localhost/tienda-prodigo/index.php?page=list`, también accesible desde «Gestión privada» al pie de la web. Desde el panel, «Ver catálogo» abre la vista en otra pestaña.

Incluye portada, categorías, catálogo con búsqueda/estado/orden, ficha, galería de miniaturas, ampliación y navegación por teclado (Escape cierra, flechas cambian imagen). «Descubrir» abre una pieza publicada al azar. Las sugerencias usan vínculos editoriales si existen, misma categoría o época aproximada, con su motivo visible.

Sólo se muestran piezas publicadas y fuera de papelera; los vendidos y fuera de venta pueden seguir visibles. El cargador inicial tiene 18 ejemplos publicados y 2 borradores: por eso la web muestra 18 de las 20 fichas, hasta que publiques las otras dos. Las imágenes públicas se validan contra el mismo estado incluso si hay sesión administrativa.

Precios y contacto siguen opcionales. Para habilitar consultas, agregar a `config/local.php` un `contact_email` o `contact_whatsapp` (país y número sólo con dígitos). Sin canal configurado no aparece un botón sin destino. No se envían mensajes automáticamente. Los precios pendientes no muestran un importe.

La V1 no necesita migraciones SQL. Sigue siendo una instalación local: estos archivos no publican la web en internet.

## Cargar 20 piezas de ejemplo

Actualizar el código y entrar al panel. En el menú **Ejemplos**, pulsar **Cargar los 20 ejemplos**. Se generan dos fichas por categoría, cada una con dos ilustraciones esquemáticas (vista general y detalle), campos específicos y datos ficticios. Los códigos empiezan con `DEMO-` y los títulos con `[DEMO]`.

No se importa SQL ni se requieren nuevas tablas. Repetir la carga agrega sólo los códigos que falten; no sobrescribe fichas ni restaura las que enviaste a papelera. Desde la misma pantalla podés enviar todos los ejemplos a papelera o restaurarlos. Los datos y las imágenes se conservan. Podés editarlos como cualquier ficha para probar el panel.

Las imágenes se dibujan localmente con GD: no se descargan archivos externos. Las fechas, atribuciones y estados son ficticios. No usar estas fichas como descripción de objetos reales.

## Fotografías

- JPG, PNG y WebP; no TIFF ni SVG.
- Hasta 12 archivos por carga, 15 MB y 12 megapíxeles por foto.
- Se generan miniatura (hasta 420 px), web (hasta 1600 px) y zoom (hasta 2400 px), sin ampliar una imagen pequeña.
- Las copias son JPEG, sin metadatos EXIF, con fondo blanco para transparencias. Si EXIF está disponible, se corrige la orientación del JPEG.
- **Los originales no se almacenan:** conservarlos aparte. Respaldar `storage/images` junto con la base de datos.
- Elegir «Eliminar foto» descarta los cambios no guardados de la ficha; confirmar después de guardar lo que se quiera conservar. Para quitar una portada de una ficha publicada, guardar otra portada o pasarla a borrador primero.

Si falla una carga por tamaño, revisar `C:\xampp\php\php.ini`:

```ini
upload_max_filesize=15M
post_max_size=200M
max_file_uploads=20
memory_limit=256M
```

Activar extensiones `pdo_mysql`, `mbstring`, `fileinfo` y `gd`; EXIF es opcional. Reiniciar Apache después de modificar PHP. El límite de PHP puede ser menor que el del panel; subir pocas fotos por vez es una alternativa.

## Actualizar el código

Copiar los nuevos archivos sobre la carpeta existente, conservando `config/local.php` y `storage`. No borrar la carpeta de imágenes ni importar de nuevo los SQL iniciales. Las futuras versiones indicarán si requieren una migración nueva.

## Límites de esta versión

Incluye acceso, fichas, campos de las categorías iniciales, fotos, filtros, previsualización y papelera. La edición de definiciones de categorías, la edición de relaciones/recorridos, la exportación y el seguimiento comercial están modelados pero sus pantallas todavía no están implementadas. El diseño es funcional y provisional.

La instalación inicial sólo se permite desde loopback (`localhost`). Para hospedar en internet habrá que configurar usuario de base propio, HTTPS, respaldos y document root seguro. Apache debe respetar los `.htaccess` incluidos para bloquear `app`, `config`, `database`, `tests` y `storage`. No publicar con las credenciales de XAMPP. Las imágenes se entregan por `index.php?page=media` tras validar la sesión.

## Si algo falla

Revisar que Apache y MySQL estén activos, confirmar la conexión y consultar `storage/errors.log`. No compartir ese archivo completo si contiene rutas o datos privados. La prueba del modelo pasó en la instalación del usuario con MariaDB 10.4.32; esto no sustituye la prueba del panel con piezas reales.
