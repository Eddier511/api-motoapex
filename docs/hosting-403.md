# Diagnóstico del 403 de Hostinger

## Evidencia y límite del diagnóstico

Comprobación externa del sitio temporal: `/`, `/index.php`, `/public/index.php`, `/v1/health`, `/v1/public/brands`, `/v1/public/categories` y `/v1/public/motorcycles` devuelven 403, `Content-Type: text/html`, servidor `hcdn`, HTML «403 Forbidden» y ningún `X-Request-ID`. No se enviaron leads, credenciales ni tokens. Esto sitúa el bloqueo antes de la respuesta PHP de la API: sus rechazos llevan JSON. `hcdn` identifica la capa que respondió, no prueba por sí solo si el rechazo se originó en CDN o en el servidor.

Sin acceso al administrador de archivos, configuración del sitio ni logs, no se puede confirmar document root, propietario, permisos efectivos o contenido instalado. Posibles causas: `.htaccess` raíz copiado a la carpeta pública o heredado, directorio público incorrecto, archivos ausentes, permisos/propietario o reglas hPanel/CDN. Un GET directo a index.php también falla, por lo que no basta atribuirlo solo al rewrite de /v1.

La captura posterior del usuario muestra `public_html` con `public`, `src`, `bin`, `database`, `tests` y un `.htaccess` de 19 bytes. Esa estructura corresponde al repositorio completo dentro del directorio público, en lugar del layout del paquete de despliegue. No aparece un index.php directamente en public_html en la captura. El tamaño del .htaccess coincide con el archivo raíz `Require all denied`; falta leer su contenido y el log del servidor para confirmar la regla efectiva. Los permisos visibles de carpetas son 755 y del .htaccess 644: la captura no evidencia un fallo en esos permisos concretos.

El repositorio tiene `.htaccess` raíz con `Require all denied`, una protección intencional de archivos privados. El `.htaccess` público anterior no declaraba su acceso explícitamente. Además, el artefacto previo de CI conservaba `public/`, mientras el ZIP local usaba `public_html/`: eran dos layouts distintos. Esa inconsistencia facilitaba subir la carpeta equivocada. La corrección unifica ambos paquetes y declara acceso en la carpeta pública; no abre la raíz privada ni cambia el contrato.

## Corrección preparada (no desplegada)

El ZIP se extrae en la carpeta **que contiene** `public_html`, sin un nivel `api-motoapex/` adicional:

```text
carpeta-del-sitio/
  config.local.php             configuración existente, privada
  src/                        código privado
  bin/                        comandos privados
  database/                   migración privada
  public_html/                document root del sitio API
    index.php                 copia de public/index.php
    .htaccess                 copia de public/.htaccess
```

El frontend no se instala en este sitio. El `.htaccess` público añade `DirectoryIndex index.php`, acceso explícito, paso de Authorization y rewrite de /v1 a index.php. Mantiene prohibidos listados, dotfiles y archivos de configuración/backups. El `.htaccess` privado raíz no se incluye en el ZIP de despliegue. Si fue copiado a public_html, respaldarlo fuera de la carpeta pública y reemplazarlo por el correcto. No eliminar protecciones de otros sitios.

Para corregir la estructura de la captura, primero mover `src`, `bin`, `database`, `docs`, `tests` y cualquier configuración privada a la carpeta superior del sitio, conservando los datos y respaldos fuera del alcance HTTP. Luego colocar `public/index.php` y el `.htaccess` público corregido directamente en public_html. No abrir el acceso general mientras queden configuraciones/tests dentro del document root. No incluir config.local.php en el ZIP ni sobrescribirlo. Si hPanel impide acceder a la carpeta superior, completar el movimiento mediante SFTP/SSH antes de habilitar la entrada pública.

En hPanel, verificar que este sitio PHP apunta al `public_html` que contiene index.php. No debe apuntar al repositorio, a src, a una carpeta vacía ni a public_html/public_html. En un despliegue con document root configurable también puede apuntar a `repo/public`, manteniendo src/config fuera de ese document root.

Revisar propietario y permisos: carpetas transitables 755, archivos públicos 644. Configuración privada 600 si el proceso PHP corre como su propietario; comprobar lectura por PHP sin moverla al directorio público. No usar 777. Si persiste 403, consultar los logs de Hostinger para distinguir «client denied by server configuration», permisos de filesystem o bloqueo de seguridad. Revisar caché de CDN después de corregir el origen; no desactivar firewall/WAF globalmente. Mantener HTTPS y PHP con PDO MySQL. No reimportar el esquema inicial ni repetir una migración ya aplicada.

## Verificación después de un despliegue autorizado

Ejecutar `python tests/verify-hosting.py` para GET de health/catálogo y OPTIONS de leads; el script es de solo lectura y no contiene POST, contraseñas ni tokens.

- `/v1/health`: 200 JSON `{data:{status:"ok"}}`.
- Catálogo público: 200 JSON con data array (vacío es válido), IDs como cadenas, estados del contrato, enlaces HTTPS, specs y precios ausentes si showPrice=false.
- Con Origin `https://wheat-stinkbug-153908.hostingersite.com`: `Access-Control-Allow-Origin` igual al origen exacto, sin wildcard; expone `Retry-After` y `X-Request-ID`.
- OPTIONS de `/v1/public/leads`: 204, permite POST y Content-Type, sin crear una solicitud.
- Sin credenciales `/v1/admin/motorcycles`: 401 JSON. Un origen no permitido: 403 JSON, no HTML.
- Leads 201, allowQuote y 429 se comprueban contra MySQL aislado en CI. Crear un lead real requiere autorización explícita; aquí no se crea.

La respuesta HTTP real sigue pendiente mientras no se aplique la corrección al servidor. Las pruebas con PHP built-in no ejercitan `.htaccess`; por eso CI agrega Apache con rewrite y autorización reales, incluido un `.htaccess` privado padre que deniega todo.

## Migración al dominio definitivo

Asignar `api.motoapexcr.com` al mismo sitio/document root y habilitar su SSL. Repetir health, catálogo y preflight con los orígenes definitivos; mantener CORS exacto para `https://motoapexcr.com` y `https://admin.motoapexcr.com`. Cambiar VITE_API_BASE_URL a `https://api.motoapexcr.com/v1` y recompilar los dos frontends. Conservar IDs, base y contrato. Retirar los orígenes temporales solo después de completar la transición. No incluir credenciales en Vite ni redirigir POST entre dominios como sustituto de actualizar la URL.

Fuentes: [Hostinger: permisos](https://www.hostinger.com/support/1583244-how-to-set-access-rights-for-files-and-folders-in-hostinger/), [Hostinger: 403](https://www.hostinger.com/support/1583304-how-to-fix-a-403-forbidden-error-at-hostinger/), [Apache: acceso denegado por configuración](https://cwiki.apache.org/confluence/spaces/HTTPD/pages/115522258/ClientDeniedByServerConfiguration).
