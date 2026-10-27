# Correcciones de seguridad — 27 de octubre de 2026

Este paquete propone cambios de aplicación, pruebas y configuración de referencia. No instala PHP, modifica Hostinger, rota credenciales ni hace merge/despliegue automáticamente. Se basa en la rama completa codex/accounts-security; los PR anteriores siguen siendo dependencias hasta que se integren.

## Instalación en Hostinger

1. Respalda base y archivos. Aplica únicamente las migraciones pendientes según schema_migrations: 002 → 003 → 004 → 005 → **006_security_hardening.sql**. No reimportes schema.sql. 006 crea una cola nueva y limpia bloqueos temporales heredados; no borra usuarios ni contenido. 005, si estaba pendiente, revoca sesiones antiguas.
2. Extrae el ZIP en la carpeta que **contiene public_html**. Conserva config.local.php privada. Solo index.php y .htaccess van dentro de public_html. No sobrescribas la configuración privada con los marcadores de config.example.php.
3. Exige PHP 8.3.36+, 8.4.27+ o 8.5.12+, PDO MySQL y Sodium. Los checks rechazan otras ramas hasta validarlas. Los parches del runtime son responsabilidad del proveedor; este ZIP no corrige el código nativo de PHP. Un backport con un número anterior requiere coordinación con el proveedor, no desactivar el guard desde el panel.
4. Revisa config/php-security.ini con el proveedor. Se necesitan directivas aplicadas al iniciar los trabajadores: session.upload_progress.enabled=Off, file_uploads=Off y enable_post_data_reading=Off. Mantener desactivado upload_progress también reduce el riesgo de CVE-2026-115560. No confiar en ini_set() ni .user.ini para proteger fases previas al parsing. No pegar directivas PHP arbitrarias en .htaccess. Reinicia todos los trabajadores después de cambios de inicio.
5. Configura SMTP TLS, remitente y password_reset_url como antes. Define recovery_queue_key con una clave privada aleatoria de 32 bytes en Base64. Si queda vacía, la cola deriva una clave separada a partir de mfa_encryption_key existente. No cambies la clave MFA al actualizar: perderías acceso a secretos MFA existentes. Una rotación de clave de cola necesita drenar/invalidar los trabajos pendientes previamente.
6. En las tareas programadas de Hostinger configura **cada minuto**, con el ejecutable PHP parcheado y rutas absolutas de tu cuenta:

   ```text
   /RUTA/AL/PHP_VERIFICADO /RUTA/PRIVADA/bin/process-mail-queue.php 20
   ```

   Las rutas son marcadores: sustituirlas por las del servidor, sin colocarlas en public_html. Primero comprueba esa misma orden manualmente desde terminal. No hay un endpoint web para ejecutar la cola. No crees un cron en Codex para sustituir el del servidor.
7. **Sin el worker no se enviarán correos de recuperación.** La respuesta genérica de la API confirma una solicitud encolada, no la entrega SMTP. Si falta SMTP responde 503 SMTP_NOT_CONFIGURED; si falta la clave responde 503 RECOVERY_NOT_CONFIGURED. Instalar y verificar cron es parte obligatoria de habilitar recuperación.
8. Comprueba health, login, MFA, edición autorizada, recuperación y entrega SMTP de una cuenta de prueba autorizada. No usar leads ni destinatarios reales como prueba automática. Verifica CORS con los orígenes exactos. La actualización del ZIP no rota la credencial MySQL compartida previamente: esa operación sigue pendiente en hPanel y config.local.php.

## Permisos de inventario

Cambiar existencias desde POST/PUT de motos requiere inventory.manage, además del permiso de edición de motos. Editor no puede cambiar cantidades. Omitir inventory al editar conserva la cantidad; enviarla sin cambios también permite una edición editorial. Crear una moto con cero existencias no concede permisos para ajustes posteriores. La comparación y escritura ocurren dentro de la transacción del guardado.

## Recuperación asíncrona

HTTP no busca al usuario ni contacta SMTP: valida formato/configuración/límites y encola el correo cifrado. Correos activos, inactivos e inexistentes reciben el mismo flujo público. La cola usa una clave derivada y un HMAC para coalescer solicitudes del mismo correo; no almacena correos ni tokens en texto plano.

El worker reclama trabajos mediante bloqueos y leases. Solo entonces busca una cuenta activa y genera un token aleatorio; en password_reset_tokens se almacena únicamente SHA-256. No mantiene una transacción o bloqueo de usuario durante el envío SMTP. Tras una entrega exitosa invalida enlaces anteriores. Los fallos invalidan el token del intento y permiten hasta tres intentos, con al menos un minuto entre ellos. Un proceso perdido puede recuperarse tras dos minutos. Los trabajos pendientes vencen a los 30 minutos y el enlace vence a los 30 minutos de generarse. Al completar, descartar o agotar reintentos, se elimina el payload cifrado. Los metadatos terminales se purgan después de siete días.

Una respuesta genérica no prueba entrega. Monitoriza smtp_delivery_failed, el resultado de cron y trabajos pendientes/en fallo en la base privada. No registres el destinatario, token, URI de MFA, cuerpo del mensaje ni excepciones SMTP completas. La cola admite hasta 10000 trabajos activos antes de responder 503 RECOVERY_BUSY; requiere monitorización de capacidad en el servidor.

## Login y abuso de límites

Se conserva la cuota de 10 intentos por IP/15 minutos, y los límites de MFA/recuperación. El contador por correo se aplica a **credenciales fallidas** después de verificar la contraseña; una cuenta no queda bloqueada globalmente durante 15 minutos por fallos ajenos. Credenciales válidas siguen pasando a MFA/cambio obligatorio cuando corresponde. Un cliente que agotó su propia cuota por IP debe esperar Retry-After:900. La protección contra ataques distribuidos sigue necesitando WAF, contraseñas fuertes y MFA; no se declara eliminada toda posibilidad de DoS.

## Lecturas y contrato

El formato sigue siendo {data:[...]} y el orden se conserva. Sin parámetros se permite el listado completo de hasta 250 filas candidatas. Al superar ese tamaño responde 422 PAGINATION_REQUIRED, sin truncar silenciosamente. Los clientes grandes deben usar limit=1..100 y seguir X-Next-Cursor hasta que desaparezca. X-Page-Limit confirma el límite solicitado. Ambos encabezados están expuestos por CORS.

Catálogo usa cursor de ID y orden descendente; contenido/promociones usan cursor `orden:id`, ascendente. Tratar el cursor como opaco y pasarlo con URLSearchParams. El cursor indica avance sobre filas candidatas: una página puede quedar vacía después de filtrar enlaces o relaciones inseguras, y aun así tener X-Next-Cursor. Seguirlo hasta que desaparezca. No usar el cursor como snapshot de datos que cambian simultáneamente.

Se filtran publicación, vigencia y padres activos en SQL. Las relaciones del catálogo y las promociones se cargan por lotes. El presupuesto es de 5000 filas por consulta de relaciones y 8 MiB de JSON por listado; si se excede, 422 PAGE_TOO_LARGE pide reducir limit. No se publican datos privados por superar un presupuesto. El catálogo admite 300 lecturas/IP/15 minutos; health, 60. No hay caché compartida que pudiera conservar precios ocultos o contenido retirado.

El gateway rechaza multipart y cuerpos superiores a 256 KiB antes de PHP. Esos rechazos pueden ser 403/413 del servidor con cuerpo HTML; los errores que llegan al router conservan el formato JSON. El frontend debe soportar errores de infraestructura sin confundirlos con datos. PHP y Apache eliminan X-Powered-By; un CDN que lo vuelva a añadir requiere ajuste del proveedor.

## Dependencias y verificación

dependencies.json registra versión, fuente y SHA-256 de la copia PHPMailer 7.1.1 y su licencia. bin/check-dependencies.py comprueba integridad y archivos inesperados. Con --advisories consulta OSV enviando solo paquete y versión, sin código o datos del proyecto; falla ante avisos activos o fallo de consulta, sin actualizar automáticamente. El empaquetado siempre verifica integridad.

CI prueba las tres ramas PHP soportadas y el piso de seguridad, mantiene las pruebas de módulos y añade permisos de inventario, paginación sin duplicados, consultas por lotes, login tras fallos hostiles, worker SMTP concurrente, ausencia de bloqueos durante envío, reintentos, recuperación de leases y protección Apache. Los servicios son desechables y no usan credenciales de producción.

También verifica que las marcas/categorías públicas no incluyan fechas internas en listados, detalles por ID/slug ni categorías anidadas; que las fechas sigan disponibles en administración; y que futuros campos privados no atraviesen el DTO público. La paginación incluye una promoción filtrada cuyo `data` queda vacío con cursor hacia la siguiente página visible, además de páginas terminales vacías y exposición CORS de los cursores.

`tests/mail_queue_security.php` comprueba en MySQL aislado que la cola no persista el correo en texto plano, que el identificador use HMAC, que la clave tenga separación de dominio, que cada reencolado renueve el nonce y que encolar no cree tokens de recuperación. Rechaza payloads truncados, malformados, manipulados o cifrados con otra clave. El worker se prueba además con ciphertext manipulado: no envía SMTP, agota sus reintentos y limpia payload y lease. No se publican correos, tokens ni secretos en el resultado.

Un CI exitoso certifica esa revisión en el entorno aislado, no la instalación de Hostinger. Para identificar un paquete instalado, el operador debe consultar `deployment-manifest.json` **fuera de public_html** y contrastar `sourceRevision` con la entrega; no hacer público el manifiesto. Verificar separadamente `schema_migrations` (incluida 006), el ejecutable PHP usado por HTTP y por cron, las directivas de inicio y la configuración/ejecución del worker. No inferir esas condiciones únicamente desde health o una cabecera PHP. No probar recuperación con destinatarios reales sin autorización.

Referencias primarias:
- https://www.php.net/archive/2026.php
- https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w
- https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp
- https://www.hostinger.com/blog/protecting-php2shell/
- https://github.com/PHPMailer/PHPMailer/security/advisories
