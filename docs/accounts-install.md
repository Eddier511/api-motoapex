# Instalación de cuentas, SMTP y MFA

Aplicar las migraciones incrementales 002, 003, 004 y 005 en orden, únicamente las pendientes según schema_migrations. Respaldo previo. Nunca reimportar schema.sql sobre la base existente. 005 revoca todas las sesiones previas para exigir el nuevo flujo seguro; conserva usuarios y datos.

PHP 8.3+, PDO MySQL y Sodium. PHPMailer 7.1.1 está incluido con su licencia, sin depender de Composer en Hostinger. Versión y procedencia en vendor/phpmailer/UPSTREAM.md. El ZIP coloca vendor y src fuera de public_html. Únicamente public_html/index.php y .htaccess son públicos.

Configurar en config.local.php del servidor, fuera de public_html; reiniciar PHP/limpiar OPcache después de cambiarla:

- mfa_encryption_key: clave aleatoria de 32 bytes en Base64. Generar allí con `php -r 'echo base64_encode(random_bytes(32));'`. Guardar respaldo privado. No cambiar la clave sin recifrar los secretos existentes; perderla impide validar MFA. No usar una contraseña como clave ni subirla a GitHub.
- smtp: host, port (587 STARTTLS o 465 TLS implícito), encryption `tls` o `ssl`, username, password y from. Validación de certificados activa. El remitente debe estar autorizado por el proveedor; configurar SPF/DKIM y verificar entrega. Debug SMTP está desactivado. No se permite SMTP sin cifrado en producción.
- password_reset_url: URL HTTPS exacta de la pantalla de recuperación del admin, por ejemplo https://admin.motoapexcr.com/reset-password. Para pruebas manuales previas usar el dominio temporal del admin si su ruta está implementada. El enlace usa `#token=...`; el frontend debe leer y eliminar el fragmento de la barra, mantener el token únicamente en memoria y enviarlo por POST al API. No guardar el token en analytics, logs, localStorage ni sessionStorage.

Sin SMTP completo, recuperación responde 503 SMTP_NOT_CONFIGURED para cualquier correo: instalación pendiente, no se simula envío. Cuando está configurado devuelve el mismo mensaje para correos inexistentes, inactivos y activos. Los fallos de entrega se registran como smtp_delivery_failed junto al request ID, sin destinatario, token, cuerpo ni credenciales; la respuesta pública sigue siendo genérica. Revisar estos eventos y confirmar entrega SMTP antes de conectar producción. Los correos se envían sin HTML. Los tokens vencen a los 30 minutos; se almacenan solamente como SHA-256 y cada solicitud invalida los enlaces anteriores de la cuenta.

La CI verifica SMTP con un servidor local desechable sin cifrado, permitido exclusivamente cuando environment=test y host=127.0.0.1. No configurar environment=test en Hostinger. La CI no verifica credenciales, DNS ni entrega real de Hostinger.

Los frontends aún deben implementar los flujos de docs/contract.md. El API devuelve desafíos de login antes de emitir una sesión completa. No instalar este ZIP con un admin que trate cualquier respuesta de login como token; conectar primero el manejo de mfa_login/password_change y reautenticación. Cuentas sin MFA ni cambio obligatorio mantienen `{token,expiresAt,user}`.

No existen contraseñas predeterminadas ni instalador web. Crear el administrador desde CLI con bin/create-admin.php y una contraseña propia mediante variable temporal. El panel crea usuarios con contraseña explícita de 16-72 bytes y cambio obligatorio. No permite que un administrador marque ese cambio como completado por el usuario.

Cambios de role_permissions hechos mediante SQL provocan revocación al siguiente uso de la sesión mediante huella de permisos. No hay endpoint para editar roles/permisos. Mantener al menos un administrador activo con users.manage/settings.manage al hacer cambios manuales. El CRUD serializa desactivación/eliminación/cambio de rol para proteger el último admin activo. Ediciones SQL directas pueden saltarse esa protección y no deben usarse como alternativa al CRUD.

Para el dominio definitivo actualizar bases de los frontends a https://api.motoapexcr.com/v1, allowed_origins del servidor a los orígenes exactos finales, password_reset_url y certificados. settings.site_url/admin_url/api_url son valores informativos, nunca modifican CORS, SMTP ni variables del servidor.
