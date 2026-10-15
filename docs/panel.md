# Panel de administración

La carga y edición actual se realizan en un modal ABM por pasos, con Enter para avanzar, Escape para retroceder y Tab para campos. Fotos al final; pasos de material según categorías. Ver [uso en XAMPP](xampp.md#carga-y-edición-por-teclado-abm).


## Trabajo diario

Listado con miniatura, código, título, categorías, publicación y disponibilidad. Buscar por código/título y filtrar por categoría, disponibilidad, publicación y selección. Al volver de una ficha, conservar filtros y posición. Acciones múltiples para selección, archivo y disponibilidad, con resultado visible y posibilidad de corregir.

Carga: subir fotos → título/categoría → completar lo necesario → guardar borrador → previsualizar → publicar. Guardar un borrador no exige completar todos los campos. El panel es usable desde celular para fotos y cargas breves; las funciones de edición mantienen el mismo significado en computadora.

## Editor de ficha

| Área | Contenido |
|---|---|
| Pública | Título, descripción, datación y certeza, medidas, conservación, datos específicos |
| Fotografías | Subir, ordenar, portada, función de imagen, texto alternativo y punto focal |
| Presentación | Selecciones, recorridos y relaciones con motivo y vista de destino |
| Interna | Ubicación, adquisición, costo, notas, consultas y operaciones |

Los campos de las categorías elegidas aparecen dinámicamente. Cambiar categoría no elimina datos sin una decisión explícita. Previsualizar requiere sesión administrativa y permite revisar la ficha antes de publicarla. Papelera recuperable, duplicación a borrador, indicadores de guardado y aviso al salir con cambios pendientes.

## Etapas

1. **Gestión básica:** configuración PHP/MySQL, login de un administrador, piezas, categorías, campos, borradores, fotos y papelera. Validar con piezas reales.
2. **Edición del catálogo:** vínculos, términos, selecciones y recorridos. Probar que ninguna relación revele un borrador.
3. **Frente público:** catálogo con filtros, ficha, visor y contacto con título/código precompletados; vendidos y fuera de venta claramente señalados. La estética se define antes de esta etapa.
4. **Gestión comercial:** consultas, reservas, ventas, acciones múltiples y exportación/restauración. Puede adelantarse seguimiento básico si hace falta.

No contratar hosting para revisar el modelo. La primera implementación puede correr localmente; este esquema todavía no instala un sitio ni un panel.
