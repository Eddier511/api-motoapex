# MotoApex API

API REST v1 para PHP 8.3+ y MySQL 8 (PDO MySQL). Web y admin son builds estáticos independientes; esta API se ejecuta con PHP, sin Node ni Composer. Código organizado en entrada HTTP, acceso a datos, validación, esquema SQL y pruebas.

## Instalar en Hostinger

1. Crea el sitio/subdominio de la API, activa SSL y selecciona PHP 8.3 o superior con PDO MySQL. Confirma que los tres sitios y la base están en el mismo servidor: `127.0.0.1` solo funciona desde ese servidor.
2. Descarga el artefacto `api-motoapex-hostinger` de una ejecución verde de GitHub Actions. No hay despliegue automático a producción.
3. En la carpeta del sitio, coloca `src`, `bin`, `database` y `config.local.php` **fuera de `public_html`**. Copia únicamente `public/index.php` y `public/.htaccess` dentro de `public_html`. `index.php` espera encontrar `src` un nivel por encima de la raíz pública. No copies el `.htaccess` de la raíz del repositorio a `public_html`.
4. Copia `config.example.php` como `config.local.php` en esa carpeta privada, cambia la contraseña desde el servidor y reemplaza `allowed_origins` por las URLs HTTPS exactas de web/admin. Los dominios del ejemplo aún no están confirmados. No subas esta configuración a GitHub.
5. Tu esquema inicial ya está importado. Aplica únicamente `database/002_api_support.sql`, una sola vez y después de un respaldo: agrega los límites de peticiones y campos de presentación para la web. No vuelvas a importar `schema.sql` en esa base. Para una base de prueba vacía, importa primero `schema.sql` y luego `002_api_support.sql`.
6. Desde SSH/terminal de Hostinger, define temporalmente `MOTOAPEX_ADMIN_PASSWORD` con una contraseña única de 16–72 bytes y ejecuta `php bin/create-admin.php tu-correo 'Tu nombre'`. El script solo se ejecuta por CLI. No hay instalador web ni usuario predeterminado. Si tu plan no incluye terminal, genera el hash con PHP en un entorno de confianza y crea el registro por phpMyAdmin; nunca uses un generador online de contraseñas/hashes.
7. Prueba `https://TU_API/v1/health`, login, guardado desde el admin y lectura desde la web. Las credenciales de la base no son las del usuario administrador.

## Estado de esta primera versión

Incluye autenticación, logout, identidad, permisos por rol, CRUD de marcas/categorías/motos, colores y galerías por URL HTTPS, especificaciones, recepción y seguimiento de leads. La base comienza vacía; no importa datos ficticios.

Promociones, contenido editable, carga de archivos, gestión de usuarios, recuperación de contraseña, MFA, historial de inventario y analítica de visitas quedan pendientes. No anunciar sus botones como funcionales. No se ha conectado todavía el frontend de los otros repositorios ni desplegado en Hostinger.

## Seguridad y verificación

Las contraseñas se guardan con `password_hash` y se validan con `password_verify`. Los tokens tienen 256 bits aleatorios, caducan en 8 horas por defecto y se guardan solo como SHA-256. Logout los revoca; desactivar un usuario también bloquea sus sesiones. PDO usa consultas preparadas sin emulación. La API valida tipos, tamaños, enums y URLs HTTPS; ignora campos ajenos al contrato. Las rutas privadas requieren autorización, aunque se conozca su dirección. Los logs excluyen contraseñas, tokens y datos personales de formularios.

Login: 10 intentos por IP cada 15 minutos; leads: 5 envíos por IP cada 15 minutos. La IP proviene del servidor, sin confiar en encabezados del cliente. Si Hostinger oculta la IP detrás de un proxy, valida el valor real antes de producción; varios visitantes podrían compartir el límite. CORS permite solo orígenes exactos configurados, pero no sustituye la autenticación.

GitHub Actions verifica sintaxis y pruebas HTTP contra MySQL 8 antes de empaquetar. Se prueban rutas privadas, permisos de ventas, inyección en login, origen rechazado, borradores, precios ocultos, persistencia, revocación y límite de intentos. Sin PHP/MySQL locales, esas pruebas se ejecutan en CI. Un pipeline verde no equivale a una auditoría ni garantiza seguridad absoluta.

Métricas operativas recomendadas: tasa de 5xx, fallos de login, respuestas 429, cambios administrativos y latencia p95. La versión registra eventos de login/cambios con `requestId` en el log del servidor, y cada respuesta registra estado HTTP y duración en milisegundos. Los cambios administrativos se guardan también en audit_logs, dentro de la transacción. Aún no incluye un colector de métricas, alertas ni almacenamiento de auditoría inmutable. Antes de uso con datos reales, configura monitoreo, backups con prueba de restauración, actualización de PHP, MFA de GitHub/hPanel y un usuario MySQL exclusivo para la API con permisos mínimos. Tras importar el esquema, el usuario de ejecución necesita SELECT/INSERT/UPDATE/DELETE, no DROP/GRANT/ALTER. La configuración pública de ejemplo contiene nombre y usuario de BD, nunca su contraseña.

Consulta [contrato](docs/contract.md) y [prompts de integración](docs/integration-prompts.md).
