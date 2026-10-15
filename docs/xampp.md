# Primera instalación en XAMPP

La base `prodigo` ya creada sirve para este panel. **No vuelvas a importar el esquema ni las categorías si ya lo hiciste.** No se necesitan tablas nuevas para esta versión.

1. Descargar el ZIP del repositorio (Code → Download ZIP) y descomprimir.
2. Copiar su contenido a `C:\xampp\htdocs\tienda-prodigo` (o la carpeta htdocs de tu XAMPP). `index.php` debe quedar directamente dentro de esa carpeta.
3. Iniciar Apache y MySQL desde XAMPP.
4. Abrir `http://localhost/tienda-prodigo/`.
5. Entrar a **Gestión privada** al pie del catálogo y crear la cuenta inicial con correo y contraseña de al menos 12 caracteres.

El panel se conecta a `127.0.0.1:3306`, base `prodigo`, usuario `root`, contraseña vacía: valores habituales de XAMPP local. Si tu instalación es diferente, copiar `config/example.php` como `config/local.php` y modificar esa copia. No subir `local.php` a GitHub.

## Carga y edición por teclado (ABM)

**Nueva pieza** y **Editar ficha** abren una ventana modal. El listado queda detrás y conserva sus filtros al cerrar. No se necesitan tablas nuevas para el asistente.

| Tecla | Acción |
|---|---|
| Enter | Confirma la pantalla y avanza; en la última, guarda y cierra |
| Escape | Retrocede una pantalla; en la primera cancela la carga |
| Tab / Shift+Tab | Recorren los campos y controles visibles |
| Espacio | Marca/desmarca una categoría o una opción |
| Shift+Enter | Inserta un salto de línea en un texto largo |
| Ctrl+Enter | Guarda y cierra desde cualquier pantalla, validando la ficha |

Enter sobre un botón ejecuta ese botón; sobre el selector de archivos abre la elección de fotos. Los botones y el indicador de pasos permiten también usar el mouse. Al cancelar con cambios pendientes se pide confirmar su descarte.

Orden: identificación y categorías → una pantalla por cada material elegido → descripción/datación → gestión interna → presentación → publicación → fotografías. Los campos propios del material salen de las definiciones de categorías existentes. Podés elegir varias categorías sin duplicar la ficha. Cambiar entre pantallas no borra datos; quitar una categoría con valores guardados exige confirmar la eliminación de esos datos.

**Guardar y nueva**, al final, abre otra ficha en blanco con las mismas categorías para facilitar cargas consecutivas. **Guardar y cerrar** vuelve al listado. Los borradores sólo requieren al menos una categoría; si se omite el título se guarda «Sin título». Publicar sigue requiriendo portada.

En fotografías se puede elegir portada, ordenar con las flechas o arrastrando, y editar función/textos. **Quitar foto al guardar** marca la foto para eliminarla en el mismo guardado de la ficha; **Deshacer eliminación** la conserva. Cancelar no elimina ninguna foto. Si un error impide guardar, las eliminaciones se revierten junto con los datos.

**Duplicar ficha guardada** crea una pieza con código propio, como borrador y fuera de venta. Copia datos, campos específicos, notas internas, términos y presentación. La opción **Incluir fotografías** está marcada inicialmente; genera archivos independientes, por lo que borrar una foto del original no afecta la copia. La operación toma la última versión guardada: guardar primero si querés copiar cambios recientes. No copia reservas, ventas, consultas ni el estado publicado.

**Enviar a papelera** borra de forma recuperable. La ficha y sus fotos se conservan hasta restaurarla. No hay borrado físico masivo en esta versión.

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

## Plantillas por pieza

Después de actualizar el código, abrir **Visualización** en el menú del panel. Si todavía no están habilitadas, pulsar **Habilitar plantillas** una sola vez: agrega `piece_presentations` sin modificar las tablas existentes. También es posible importar `database/004_presentations.sql` desde phpMyAdmin. Si todavía no se habilitaron, las fichas funcionan con paneles por defecto.

- **Paneles:** información a la izquierda e imagen a la derecha en computadora; imagen arriba en celular.
- **Wallpaper:** la portada ocupa el fondo y el título y un texto breve flotan encima; la ficha completa y la galería se mantienen debajo. Sin portada, se usa paneles.
- Elegir familia tipográfica para título/texto, tamaños y cursiva. Las fuentes son familias del sistema (Georgia, Palatino, Arial y Courier), sin dependencias de servicios externos; todavía no se suben archivos de fuentes propios.
- En wallpaper, elegir posición izquierda/centro/derecha, velo oscuro/claro, intensidad y punto de encuadre horizontal/vertical. El fondo se recorta para cubrir el área; la imagen completa sigue disponible en el visor.
- Escribir, si se desea, un texto breve (hasta 600 caracteres) para la portada. El texto descriptivo largo no se superpone al fondo.
- Guardar y usar **Previsualizar** para ver los ajustes, incluso en borradores. **Probar paneles** / **Probar wallpaper** comparan ambos con los datos guardados sin cambiar la elección. Estas vistas y las imágenes de borradores exigen sesión administrativa; compartir su URL no publica una ficha.

Elegir una pieza guardada y pulsar **Abrir visualización**. También se accede desde el botón **Visualización** de una ficha existente. El modal de carga sólo contiene datos y fotos: guardar la ficha conserva el diseño, y **Guardar visualización** no modifica sus datos. No hace falta ejecutar SQL nuevamente si las plantillas ya estaban habilitadas.

En móvil el fondo tiene un velo más intenso para facilitar la lectura. Los ajustes son independientes por pieza y no cambian el diseño de las tarjetas del catálogo.

## Cargar 20 piezas de ejemplo

Actualizar el código y entrar al panel. En el menú **Ejemplos**, pulsar **Cargar los 20 ejemplos**. Se generan dos fichas por categoría, cada una con dos ilustraciones esquemáticas (vista general y detalle), campos específicos y datos ficticios. Los códigos empiezan con `DEMO-` y los títulos con `[DEMO]`.

No se importa SQL ni se requieren nuevas tablas. Repetir la carga agrega sólo los códigos que falten; no sobrescribe fichas ni restaura las que enviaste a papelera. Desde la misma pantalla podés enviar todos los ejemplos a papelera o restaurarlos. Los datos y las imágenes se conservan. Podés editarlos como cualquier ficha para probar el panel.

Las imágenes se dibujan localmente con GD: no se descargan archivos externos. Las fechas, atribuciones y estados son ficticios. No usar estas fichas como descripción de objetos reales.

## Fotografías

- JPG, PNG, WebP, GIF y BMP; AVIF si GD lo admite. GIF animado se convierte como imagen fija. TIFF, HEIC, RAW y SVG no se admiten con GD.
- Para fotos grandes: `memory_limit=512M`, `upload_max_filesize=40M` y `post_max_size=128M` en php.ini; reiniciar Apache. La suma de archivos de una carga debe entrar en post_max_size.
- Hasta 12 archivos por carga, 40 MB y 50 megapíxeles (según memoria PHP) por foto.
- Se generan miniatura (hasta 420 px), web (hasta 1600 px) y zoom (hasta 2400 px), sin ampliar una imagen pequeña.
- Las nuevas copias son WebP (calidad 80 en miniaturas, 88 en web/zoom); las fotos JPEG existentes siguen funcionando, sin metadatos EXIF, con fondo blanco para transparencias. Si EXIF está disponible, se corrige la orientación del JPEG.
- **Los originales no se almacenan:** conservarlos aparte. Respaldar `storage/images` junto con la base de datos.
- Marcar una foto para quitarla aplica la eliminación al guardar toda la ficha. Si quitás todas las fotos de una pieza publicada, elegí borrador o agregá otra portada antes de guardar.

Si falla una carga por tamaño, revisar `C:\xampp\php\php.ini`:

```ini
upload_max_filesize=40M
post_max_size=200M
max_file_uploads=20
memory_limit=512M
```

Activar extensiones `pdo_mysql`, `mbstring`, `fileinfo` y `gd`; EXIF es opcional. Reiniciar Apache después de modificar PHP. El límite de PHP puede ser menor que el del panel; subir pocas fotos por vez es una alternativa.

## Actualizar el código

Copiar los nuevos archivos sobre la carpeta existente, conservando `config/local.php` y `storage`. No borrar la carpeta de imágenes ni importar de nuevo los SQL iniciales. Las futuras versiones indicarán si requieren una migración nueva.

## Límites de esta versión

Incluye acceso, fichas, campos de las categorías iniciales, fotos, filtros, previsualización y papelera. La edición de definiciones de categorías, la edición de relaciones/recorridos, la exportación y el seguimiento comercial están modelados pero sus pantallas todavía no están implementadas. El diseño es funcional y provisional.

La instalación inicial sólo se permite desde loopback (`localhost`). Para hospedar en internet habrá que configurar usuario de base propio, HTTPS, respaldos y document root seguro. Apache debe respetar los `.htaccess` incluidos para bloquear `app`, `config`, `database`, `tests` y `storage`. No publicar con las credenciales de XAMPP. Las imágenes se entregan por `index.php?page=media` tras validar la sesión.

## Si algo falla

Revisar que Apache y MySQL estén activos, confirmar la conexión y consultar `storage/errors.log`. No compartir ese archivo completo si contiene rutas o datos privados. La prueba del modelo pasó en la instalación del usuario con MariaDB 10.4.32; esto no sustituye la prueba del panel con piezas reales.
