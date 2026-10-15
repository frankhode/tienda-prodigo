# Pródigo

Catálogo de antigüedades y objetos coleccionables, con venta por contacto y recorridos de descubrimiento.

## Estado

Panel privado con **modal ABM por pasos**, navegación con teclado, duplicación opcional con fotos independientes y borrado recuperable. Incluye: acceso, carga y edición de piezas, campos por categoría, fotografías, filtros, previsualización y papelera. Incluye una V1 del catálogo para visitantes, con portada, filtros, ficha y galería. Incluye plantillas por pieza (paneles y wallpaper) con tipografías, contraste y encuadre configurables desde la pantalla independiente **Visualización**. La raíz abre el catálogo; el panel está en `index.php?page=list`. La estética, los precios, el canal de contacto y el proveedor de hosting siguen pendientes.

## Base propuesta

PHP + MySQL, compatible con desarrollo local en XAMPP y alojamiento compartido. El esquema requiere **MySQL 8.0.16 o posterior**, InnoDB y una base con `utf8mb4`. La prueba SQL incluida pasó también en MariaDB 10.4.32 del XAMPP del usuario. El panel requiere PHP 8.2+ con PDO MySQL, GD y mbstring.

1. Crear una base vacía `prodigo` con cotejamiento `utf8mb4_unicode_ci`.
2. Importar `database/001_schema.sql` en esa base.
3. Importar `database/002_categories.sql` una sola vez.
4. Ejecutar `database/003_smoke_test.sql` en una base de prueba: verifica vínculos, privacidad, conjuntos y estados y revierte los datos al terminar.

No se incluyen cuentas con contraseña por defecto ni datos personales. En **Ejemplos** podés generar 20 fichas ficticias e ilustraciones locales para probar el panel sin importar más SQL. No importar archivos de desarrollo encima de una base en uso. Las futuras modificaciones serán migraciones nuevas.

## Documentación

- **[Instalar el panel en XAMPP](docs/xampp.md).**
- [Modelo y reglas](docs/modelo.md).
- [Panel y etapas de implementación](docs/panel.md).

## Siguiente etapa

Continuar con edición de categorías/campos, relaciones y recorridos; ampliar los recorridos y definir el canal de contacto. Este repositorio almacena código y migraciones: las fichas, imágenes y respaldos privados se guardan fuera de Git.
