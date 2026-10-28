# Promociones con fechas opcionales — migración 007

Parte de la implementación completa del PR #5 (base e47b2c17fabf3fe596519ffeb1cdc7f232559651), en una rama separada codex/promotions-optional-dates. No asumir que está en main ni instalada. El contrato de docs/contract.md define cuatro combinaciones: null/null, inicio/null, null/fin y ambas fechas. Claves obligatorias, valores anulables; sin fechas inventadas ni duración predeterminada.

## Hostinger / MySQL

1. Respaldar la base y comprobar **en privado**, antes de instalar:

```sql
SELECT VERSION();
SELECT version FROM schema_migrations ORDER BY version;
SHOW CREATE TABLE promotions;
```

2. Requiere MySQL >=8.0.16 para CHECK efectivo (la suite usa MySQL 8.0). Si Hostinger devuelve MariaDB u otro motor/versión, revisar y verificar esa variante antes de instalar; no se ha comprobado el motor de producción desde esta tarea. La columna original usa DATETIME sin fracciones y se conserva ese tipo. El CHECK existente `ends_at >= starts_at` sigue válido: con un NULL evalúa UNKNOWN, permitido por MySQL, y con dos fechas invertidas evalúa FALSE, rechazado. No hace falta adivinar ni eliminar su nombre generado. Referencia: [CHECK en MySQL](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html).
3. Si 006 está aplicada y 007 no, ejecutar **una sola vez** el archivo completo `database/007_optional_promotion_dates.sql` en la base seleccionada mediante phpMyAdmin/importación o el cliente privado. No reimportar schema.sql, no borrar tablas ni alterar precios/relaciones. ALTER TABLE puede bloquear temporalmente: coordinar ventana de instalación. DDL tiene commit implícito; no prometer un rollback transaccional de la migración. Si se interrumpe entre ALTER y el marcador, comprobar columnas y schema_migrations antes de reintentar.
4. Verificar después:

```sql
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promotions'
  AND COLUMN_NAME IN ('starts_at','ends_at');
SELECT version FROM schema_migrations WHERE version='007_optional_promotion_dates';
SHOW CREATE TABLE promotions;
```

Ambas columnas deben seguir DATETIME y ahora admitir NULL. Las fechas existentes siguen idénticas. Coordinar el código de API y los frontends; el código viejo no publica correctamente campañas con NULL. El ZIP incorpora 007, pero extraerlo no ejecuta SQL. No revertir a NOT NULL mientras existan promociones sin fechas.

## Admin

Usar `startsAt: string | null` y `endsAt: string | null` en modelos, validación de respuestas y serialización. Un campo vacío de formulario se envía como null JSON, nunca `""`, `"null"`, undefined ni una fecha lejana. En POST y PUT completos enviar siempre ambas claves. Al editar conservar null; al quitar una fecha enviar null explícito. Fechas elegidas deben incluir segundos y zona: si un datetime-local no incluye zona, convertir de forma explícita según la zona del formulario antes de enviar. Comparar inicio/fin solo cuando ambos existan y mantener validación del servidor, relaciones y precios por moto. No ejecutar migraciones ni desplegar automáticamente.

## Web

Aceptar null en ambas fechas al validar DTO. No filtrar una campaña pública por faltar fechas: la API aplica vigencia y publicación. Mostrar «Hasta el…» únicamente si endsAt es una fecha válida; con null ocultar toda la leyenda de vencimiento. No llamar Date/format sobre null, no renderizar Invalid Date ni inventar una duración. Conservar tarjetas, varias motos, precios omitidos y allowQuote. No usar contenido ficticio como fallback. No desplegar automáticamente.

## Verificaciones aisladas

La suite crea registros con fechas/precios/medios/relaciones **antes** de aplicar 007 y verifica que todos sus campos y CHECK se conserven. Comprueba anulabilidad y rechazo SQL de fechas invertidas. Congela solo la sesión SQL desechable para validar inicio/fin exactos (inclusivos), antes y después de esos límites, con las mismas expresiones usadas por listas y detalle. HTTP comprueba POST/PUT con las cuatro combinaciones, null explícito, lectura por ID/slug, listados, fechas con zona, 422 sin modificaciones, permisos, publicación, precios ocultos, allowQuote y eliminación lógica. No se modificó producción ni se publicaron campañas de ejemplo.
