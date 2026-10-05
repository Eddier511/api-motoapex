# InstalaciÃ³n de cuentas, SMTP y MFA

Aplicar las migraciones incrementales 002, 003, 004 y 005 en orden, Ãºnicamente las pendientes segÃºn schema_migrations. Respaldo previo. Nunca reimportar schema.sql sobre la base existente. 005 revoca todas las sesiones previas para exigir el nuevo flujo seguro; conserva usuarios y datos.

PHP 8.3+, PDO MySQL y Sodium. PHPMailer 7.1.1 estÃ¡ incluido con su licencia, sin depender de Composer en Hostinger. VersiÃ³n y procedencia en vendor/phpmailer/UPSTREAM.md. El ZIP coloca vendor y src fuera de public_html. Ãšnicamente public_html/index.php y .htaccess son pÃºblicos.

Configurar en config.local.php del servidor, fuera de public_html:

- mfa_encryption_key: clave aleatoria de 32 bytes en Base64. Generar allÃ­ con `php -r 'echo base64_encode(random_bytes(32));'`. Guardar respaldo privado. No cambiar la clave sin recifrar los secretos existentes; perderla impide validar MFA. No usar una contraseÃ±a como clave ni subirla a GitHub.
- smtp: host, port (587 STARTTLS o 465 TLS implÃ­cito), encryption `tls` o `ssl`, username, password y from. ValidaciÃ³n de certificados activa. El remitente debe estar autorizado por el proveedor; configurar SPF/DKIM y verificar entrega. Debug SMTP estÃ¡ desactivado. No se permite SMTP sin cifrado en producciÃ³n.
- password_reset_url: URL HTTPS exacta de la pantalla de recuperaciÃ³n del admin, por ejemplo https://admin.motoapexcr.com/reset-password. Para pruebas manuales previas usar el dominio temporal del admin si su ruta estÃ¡ implementada. El enlace usa `#token=...`; el frontend debe leer y eliminar el fragmento de la barra, mantener el token Ãºnicamente en memoria y enviarlo por POST al API. No guardar el token en analytics, logs, localStorage ni sessionStorage.

Sin SMTP completo, recuperaciÃ³n responde 503 SMTP_NOT_CONFIGURED para cualquier correo: instalaciÃ³n pendiente, no se simula envÃ­o. Cuando estÃ¡ configurado devuelve el mismo mensaje para correos inexistentes, inactivos y activos. Los fallos de entrega se registran como smtp_delivery_failed junto al request ID, sin destinatario, token, cuerpo ni credenciales; la respuesta pÃºblica sigue siendo genÃ©rica. Revisar estos eventos y confirmar entrega SMTP antes de conectar producciÃ³n. Los correos se envÃ­an sin HTML. Los tokens vencen a los 30 minutos; se almacenan solamente como SHA-256 y cada solicitud invalida los enlaces anteriores de la cuenta.

La CI verifica SMTP con un servidor local desechable sin cifrado, permitido exclusivamente cuando environment=test y host=127.0.0.1. No configurar environment=test en Hostinger. La CI no verifica credenciales, DNS ni entrega real de Hostinger.

Los frontends aÃºn deben implementar los flujos de docs/contract.md. El API devuelve desafÃ­os de login antes de emitir una sesiÃ³n completa. No instalar este ZIP con un admin que trate cualquier respuesta de login como token; conectar primero el manejo de mfa_login/password_change y reautenticaciÃ³n. Cuentas sin MFA ni cambio obligatorio mantienen `{token,expiresAt,user}`.

No existen contraseÃ±as predeterminadas ni instalador web. Crear el administrador desde CLI con bin/create-admin.php y una contraseÃ±a propia mediante variable temporal. El panel crea usuarios con contraseÃ±a explÃ­cita de 16-72 bytes y cambio obligatorio. No permite que un administrador marque ese cambio como completado por el usuario.

Cambios de role_permissions hechos mediante SQL provocan revocaciÃ³n al siguiente uso de la sesiÃ³n mediante huella de permisos. No hay endpoint para editar roles/permisos. Mantener al menos un administrador activo con users.manage/settings.manage al hacer cambios manuales. El CRUD serializa desactivaciÃ³n/eliminaciÃ³n/cambio de rol para proteger el Ãºltimo admin activo. Ediciones SQL directas pueden saltarse esa protecciÃ³n y no deben usarse como alternativa al CRUD.

Para el dominio definitivo actualizar bases de los frontends a https://api.motoapexcr.com/v1, allowed_origins del servidor a los orÃ­genes exactos finales, password_reset_url y certificados. settings.site_url/admin_url/api_url son valores informativos, nunca modifican CORS, SMTP ni variables del servidor.
