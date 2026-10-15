# Modelo inicial de Pródigo

## Decisiones de partida

Una ficha representa una pieza única o un conjunto que se comercializa como una unidad. Las diez categorías iniciales son editables y una pieza puede pertenecer a varias. Premium, accesible e interés cultural son selecciones editoriales superpuestas, no categorías ni rangos de precios automáticos. Sus nombres públicos quedan por definir.

Publicación y disponibilidad son independientes:

| Dimensión | Estados | Consecuencia |
|---|---|---|
| Publicación | Borrador, publicado, archivado | Sólo publicado aparece en la web |
| Disponibilidad | Disponible, reservado, vendido, fuera de venta | Vendidos y fuera de venta pueden continuar en el catálogo |
| Papelera | Fecha de eliminación o vacía | Oculta la ficha sin destruir vínculos ni imágenes |
| Precio | Pendiente, visible, a consultar, sin precio | Pendiente no produce un mensaje público; la decisión comercial sigue abierta |

No hay carrito ni pago integrado en esta etapa. Una consulta puede referirse a varias piezas.

## Entidades

| Tabla | Responsabilidad |
|---|---|
| `pieces` | Ficha común, datación, conservación, publicación y disponibilidad |
| `piece_private` | Ubicación, adquisición, costo y notas internas |
| `categories`, `piece_categories` | Categorías editables y pertenencias múltiples |
| `category_fields`, `piece_field_values` | Campos opcionales propios de una categoría |
| `images`, `image_variants`, `piece_cover` | Orden, función, texto alternativo, encuadre y portada |
| `terms`, `piece_terms` | Facetas compartidas que cruzan categorías |
| `piece_relations` | Vínculos editoriales con motivo explícito |
| `collections`, `collection_pieces` | Selecciones, recorridos y exposiciones ordenadas |
| `inquiries`, `inquiry_pieces` | Seguimiento privado de contactos |
| `transactions` | Reservas y ventas registradas manualmente |
| `admins` | Acceso al panel; inicialmente una cuenta |

Los campos específicos se guardan como texto; el servicio valida su tipo según la definición. Esto facilita agregar campos desde el panel. Si un atributo requiere filtros numéricos intensivos, podrá migrarse a una columna tipada. Las medidas se guardan inicialmente como texto para admitir unidades y formatos diversos.

## Fotografías y almacenamiento

Cada imagen puede ser de presentación, descriptiva o detalle. La portada debe pertenecer a la propia pieza, restricción garantizada por clave compuesta. El punto focal permite adaptar el encuadre sin modificar la foto. Una imagen tiene variantes miniatura, web y zoom con dimensiones y tamaño registrados.

Guardar una clave relativa y el proveedor (`local` inicialmente), nunca rutas de Windows ni enlaces temporales firmados. Un servicio de almacenamiento resolverá la URL; permitirá migrar a almacenamiento externo sin cambiar fichas. Los originales se conservarán aparte con respaldo. La duplicación crea un borrador con código propio; permite incluir fotos mediante una opción explícita, copiando sus archivos para que original y copia sean independientes. No duplica operaciones comerciales.

## Descubrimiento

1. Vínculos elegidos por el administrador: históricos, visuales, evocativos o personales, con explicación obligatoria. Una asociación creativa se presenta como tal; no se transforma en afirmación histórica.
2. Afinidades sugeridas por términos compartidos, con explicación del término. Se calculan, no se guardan como vínculos manuales. La ponderación queda por definir.
3. Recorridos ordenados y selecciones que pueden reunir categorías diferentes.
4. Descubrimiento aleatorio entre piezas públicas; la opción de sólo disponibles puede activarse.

Los vínculos son dirigidos. El panel podrá crear ambos sentidos en una misma transacción y permitir textos distintos. No se admiten autorrelaciones ni duplicados del mismo tipo. Cada consulta pública filtra también el destino: ningún borrador, archivado o elemento en papelera debe revelarse a través de relaciones, términos o recorridos. Los vendidos continúan siendo destinos válidos.

## Reglas del servicio a implementar

- Crear código estable desde el ID (`PR-000001`) dentro de una transacción; no reutilizar códigos al borrar ni calcularlos con `MAX()+1`. La columna admite importaciones con códigos propios.
- Guardar un borrador con título y al menos una categoría; el código y slug se generan. El título provisional puede ser «Sin título».
- Publicar exige categoría y portada con una variante web lista. La base permite borradores incompletos; estas condiciones las valida el servicio.
- Desactivar categorías o campos conserva sus valores anteriores. No borrar definiciones utilizadas.
- Reserva/venta: bloquear la pieza con `SELECT ... FOR UPDATE`, comprobar estado y actualizar operación y disponibilidad juntos. Sólo una operación activa por pieza; los conjuntos se reservan completos.
- Cancelar reserva conserva historial y devuelve la pieza a disponible. Una venta anulada requiere registrar el motivo antes de reabrir disponibilidad.
- Las reservas vencidas requieren proceso o revisión explícita; no asumir que el reloj modifica la base.
- Publicar no cambia automáticamente la disponibilidad. «Fuera de venta» es el valor inicial prudente de un borrador.
- Archivar y enviar a papelera son reversibles. El borrado físico será una tarea separada que respete referencias e historial.
- El precio visible requiere importe y moneda ISO 4217; los demás modos no guardan importe público. El precio realizado puede quedar sólo en la operación privada.

## Privacidad y portabilidad

La vista `public_pieces` enumera campos públicos explícitamente. La API pública deberá usar proyecciones equivalentes para relaciones, imágenes y selecciones; nunca `SELECT *` de tablas privadas. Los borradores se previsualizan únicamente en sesión administrativa. No publicar costos, ubicación física, contacto, adquisición privada ni notas de operaciones.

Autenticación futura con hash de contraseña, sesiones seguras, CSRF, límite de intentos y consultas preparadas. Validar archivos por contenido, generar nombres propios y evitar ejecución de archivos subidos. Configuración y secretos fuera del directorio público y de Git.

Respaldar base y archivos en un destino independiente. Exportar todas las entidades con IDs estables, relaciones y orden editorial, junto con manifiesto de imágenes. Probar restauración antes de producción. GitHub no es respaldo de la base de datos.

## Pendientes

Estética; precios; contacto (WhatsApp, correo u otro); hosting/dominio; límite de imágenes; política de reservas y envíos; nombres editoriales; reglas de ranking de afinidades. Ninguno impide implementar borradores y catálogo interno.
