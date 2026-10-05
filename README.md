# MotoApex API

API REST v1 para PHP 8.3+ y MySQL 8 (PDO MySQL). Web y admin son builds estÃ¡ticos independientes; esta API se ejecuta con PHP, sin Node ni Composer. CÃ³digo organizado en entrada HTTP, acceso a datos, validaciÃ³n, esquema SQL y pruebas.

## Instalar en Hostinger

1. Crea el sitio/subdominio de la API, activa SSL y selecciona PHP 8.3 o superior con PDO MySQL y Sodium. Confirma que los tres sitios y la base estÃ¡n en el mismo servidor: `127.0.0.1` solo funciona desde ese servidor.
2. Descarga el artefacto `api-motoapex-hostinger` de una ejecuciÃ³n verde de GitHub Actions. CI y el generador local usan el mismo layout `public_html/`; no se debe subir el ZIP del cÃ³digo fuente de GitHub. No hay despliegue automÃ¡tico a producciÃ³n.
3. En la carpeta del sitio, coloca `src`, `vendor`, `bin`, `database` y `config.local.php` **fuera de `public_html`**. Copia Ãºnicamente `public/index.php` y `public/.htaccess` dentro de `public_html`. `index.php` espera encontrar `src` un nivel por encima de la raÃ­z pÃºblica. No copies el `.htaccess` de la raÃ­z del repositorio a `public_html`.
4. Copia `config.example.php` como `config.local.php` en esa carpeta privada, cambia la contraseÃ±a desde el servidor y reemplaza `allowed_origins` por las URLs HTTPS exactas de web/admin. Los cuatro orÃ­genes de config.example.php estÃ¡n confirmados: sitios temporales de Hostinger y dominios definitivos. Retira los temporales cuando finalice la migraciÃ³n. No subas esta configuraciÃ³n a GitHub.
5. Tu esquema inicial ya estÃ¡ importado. Aplica Ãºnicamente `database/002_api_support.sql`, una sola vez y despuÃ©s de un respaldo: agrega los lÃ­mites de peticiones y campos de presentaciÃ³n para la web. No vuelvas a importar `schema.sql` en esa base. Para una base de prueba vacÃ­a, importa primero `schema.sql` y luego `002_api_support.sql`.
6. Desde SSH/terminal de Hostinger, define temporalmente `MOTOAPEX_ADMIN_PASSWORD` con una contraseÃ±a Ãºnica de 16â€“72 bytes y ejecuta `php bin/create-admin.php tu-correo 'Tu nombre'`. El script solo se ejecuta por CLI. No hay instalador web ni usuario predeterminado. Si tu plan no incluye terminal, genera el hash con PHP en un entorno de confianza y crea el registro por phpMyAdmin; nunca uses un generador online de contraseÃ±as/hashes.
7. Prueba `https://darksalmon-quetzal-730302.hostingersite.com/v1/health`, login, guardado desde el admin y lectura desde la web. Las credenciales de la base no son las del usuario administrador.

## Estado de esta primera versiÃ³n

Incluye autenticaciÃ³n, logout, identidad, permisos por rol, CRUD de marcas/categorÃ­as/motos, colores y galerÃ­as por URL HTTPS, especificaciones, recepciÃ³n y seguimiento de leads. La base comienza vacÃ­a; no importa datos ficticios.

Promociones ahora incluyen CRUD real, precios por moto, vigencia, permisos y eliminaciÃ³n lÃ³gica. Requieren aplicar 003_promotions.sql despuÃ©s de 002, una sola vez. El contenido editable, carga de archivos, gestiÃ³n de usuarios, recuperaciÃ³n de contraseÃ±a, MFA, historial de inventario y analÃ­tica de visitas siguen pendientes. La conexiÃ³n de promociones a los frontends y la verificaciÃ³n en Hostinger siguen pendientes; no se despliegan automÃ¡ticamente.

## Seguridad y verificaciÃ³n

Las contraseÃ±as se guardan con `password_hash` y se validan con `password_verify`. Los tokens tienen 256 bits aleatorios, caducan en 8 horas por defecto y se guardan solo como SHA-256. Logout los revoca; desactivar un usuario tambiÃ©n bloquea sus sesiones. PDO usa consultas preparadas sin emulaciÃ³n. La API valida tipos, tamaÃ±os, enums y URLs HTTPS; ignora campos ajenos al contrato. Las rutas privadas requieren autorizaciÃ³n, aunque se conozca su direcciÃ³n. Los logs excluyen contraseÃ±as, tokens y datos personales de formularios.

Login: 10 intentos por IP cada 15 minutos; leads: 5 envÃ­os por IP cada 15 minutos. La IP proviene del servidor, sin confiar en encabezados del cliente. Si Hostinger oculta la IP detrÃ¡s de un proxy, valida el valor real antes de producciÃ³n; varios visitantes podrÃ­an compartir el lÃ­mite. CORS permite solo orÃ­genes exactos configurados, pero no sustituye la autenticaciÃ³n.

GitHub Actions verifica sintaxis y pruebas HTTP contra MySQL 8 antes de empaquetar. Se prueban rutas privadas, permisos de ventas, inyecciÃ³n en login, origen rechazado, borradores, precios ocultos, persistencia, revocaciÃ³n y lÃ­mite de intentos. Sin PHP/MySQL locales, esas pruebas se ejecutan en CI. Un pipeline verde no equivale a una auditorÃ­a ni garantiza seguridad absoluta.

MÃ©tricas operativas recomendadas: tasa de 5xx, fallos de login, respuestas 429, cambios administrativos y latencia p95. La versiÃ³n registra eventos de login/cambios con `requestId` en el log del servidor, y cada respuesta registra estado HTTP y duraciÃ³n en milisegundos. Los cambios administrativos se guardan tambiÃ©n en audit_logs, dentro de la transacciÃ³n. AÃºn no incluye un colector de mÃ©tricas, alertas ni almacenamiento de auditorÃ­a inmutable. Antes de uso con datos reales, configura monitoreo, backups con prueba de restauraciÃ³n, actualizaciÃ³n de PHP, MFA de GitHub/hPanel y un usuario MySQL exclusivo para la API con permisos mÃ­nimos. Tras importar el esquema, el usuario de ejecuciÃ³n necesita SELECT/INSERT/UPDATE/DELETE, no DROP/GRANT/ALTER. La configuraciÃ³n pÃºblica de ejemplo contiene nombre y usuario de BD, nunca su contraseÃ±a.

Consulta [contrato](docs/contract.md) y [prompts de integraciÃ³n](docs/integration-prompts.md).
Para el bloqueo 403 del sitio temporal, consulta [diagnÃ³stico y correcciÃ³n de hosting](docs/hosting-403.md). El contrato no cambia.

## Compatibilidad del esquema recibido

La importaciÃ³n de prueba en MySQL 8 detectÃ³ que motorcycle_color_images usa ON DELETE CASCADE sobre color_id, base de una columna generada STORED. MySQL rechaza esa combinaciÃ³n. schema.sql cambia esa relaciÃ³n a RESTRICT para instalaciones nuevas; la API usa borrado lÃ³gico y elimina explÃ­citamente las imÃ¡genes al reemplazar una galerÃ­a. No se modifica automÃ¡ticamente la base de Hostinger ya importada. Si Hostinger usa MariaDB y completÃ³ la importaciÃ³n, conserva su estructura; si hubo errores de importaciÃ³n, verifica SHOW TABLES y SHOW CREATE TABLE motorcycle_color_images antes de aplicar la migraciÃ³n adicional.

## URLs confirmadas

| Servicio | Temporal | Definitiva |
| --- | --- | --- |
| Web | https://wheat-stinkbug-153908.hostingersite.com | https://motoapexcr.com |
| Admin | https://darkorange-ant-895420.hostingersite.com | https://admin.motoapexcr.com |
| API | https://darksalmon-quetzal-730302.hostingersite.com/v1 | https://api.motoapexcr.com/v1 |

CORS permite los orÃ­genes de web/admin, no necesita agregar el dominio de la API. Tener el sitio creado no confirma que el backend estÃ© instalado; verificar /v1/health despuÃ©s del despliegue.

Usuarios, configuración, recuperación SMTP y MFA están implementados en el PR de cuentas. Aplicar 005_accounts.sql después de 004; revoca sesiones existentes. Consultar docs/contract.md y docs/accounts-install.md para configurar SMTP y cifrado sin exponer secretos.
