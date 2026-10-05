# Contrato API v1

Base: `https://darksalmon-quetzal-730302.hostingersite.com/v1`. Solicitudes con cuerpo: `Content-Type: application/json`. Respuestas: `{ "data": ... }` o `{ "error": { "code": "...", "message": "..." } }`. Los errores son 400/401/403/404/405/409/413/415/422/429/500. El encabezado `X-Request-ID` permite localizar errores sin publicar detalles internos.

## Autenticación

- `POST /auth/login`: `{ "email": "...", "password": "..." }`; devuelve `{token, expiresAt, user}`. `user` usa campos DB: `id`, `name`, `email`, `role`, `status`, `lastAccess`.
- `GET /auth/me`: usuario de la sesión.
- `POST /auth/logout`: cuerpo `{}`; revoca el token actual.

Todas las rutas privadas usan `Authorization: Bearer TOKEN`. Mantén el token en memoria; al recargar se requiere iniciar sesión. No guardarlo en localStorage, URLs ni logs. Si se necesita sesión persistente, implementar posteriormente cookies HttpOnly, Secure y protección CSRF según los dominios reales. No insertar contenido API con `dangerouslySetInnerHTML`.

## Catálogo

`GET /public/brands`, `/public/categories`, `/public/motorcycles` y sus variantes `/{id-o-slug}`. Solo se publican marcas/categorías activas y motos con `published=true` cuyo brand/category siga activo. Los listados devuelven arrays. Esta versión no tiene búsqueda ni paginación; usar filtros del frontend para catálogos pequeños.

Admin: `GET /admin/{brands|categories|motorcycles}`, `GET /admin/{kind}/{id-o-slug}`, `POST /admin/{kind}`, `PUT /admin/{kind}/{id-o-slug}` (reemplazo completo), `DELETE /admin/{kind}/{id-o-slug}`.

`admin` y `editor` crean/editan; `marketing` consulta. Publicar o retirar publicación requiere motorcycles.publish (por defecto solo admin). También se comprueban los permisos de role_permissions. Solo `admin` elimina. Las marcas/categorías en uso no se eliminan; desactivarlas. La API devuelve IDs numéricos del esquema MySQL como cadenas y fechas UTC. Slugs únicos por tipo. Referenciar IDs devueltos por la API, nunca los IDs mock del otro proyecto.

Marca: `name`, `slug` requeridos; `description`, `status` (active/inactive, defecto inactive), `order` entero, `primaryColor`, `secondaryColor`, `accentLight` (#RRGGBB), `logo`, `heroImageUrl`, `tileImageUrl` (HTTPS o vacío), `tagline`, `slogan`. La respuesta pública agrega `categories` de esa marca y categorías generales.

Categoría: `name`, `slug`, `description`, `status`, `order`, `brandId` opcional (vacío = general).

Moto, ejemplo mínimo para crear borrador:

```json
{
  "slug": "ktm-390-duke-2026",
  "brandId": "ID_MARCA",
  "categoryId": "ID_CATEGORIA",
  "model": "390 Duke",
  "year": 2026,
  "price": 7800000,
  "currency": "CRC",
  "published": false,
  "showPrice": true,
  "allowQuote": true
}
```

Otros campos: `version`, `sku`, `tagline`, `shortDescription`, `description`, `displacement`, `inventory` entero no negativo, `hp`, `promoPrice` número o null, `status` available/reserved/sold_out/coming_soon, `featured`, `isNew`, `specs` [{group,label,value}], `colors` [{id,name,hex,status,available,order,images:[{id,url,alt,label,order,isPrimary}]}]. IDs de colores/imágenes: cadenas únicas generadas por el frontend. Máximo 30 colores, 30 imágenes por color, 100 especificaciones. Orden numérico; una sola imagen principal por color. Cuerpo máximo 256 KiB. Los booleanos deben ser booleanos JSON, los números deben ser números.

Respuesta privada añade `brand`, `category`, `id`, `createdAt`, `updatedAt`. Respuesta pública transforma `featured→isFeatured`, `displacement→cc`, `colors→colorOptions`, incorpora `brandName`, `brandColor`, `categoryName`; `availability` usa available/reserved/coming-soon/sold-out. **reserved no equivale a pre-order**: agregar ese estado al tipo web. Solo incluye colores activos. `price` y `promoPrice` se omiten cuando `showPrice=false`; no publica SKU/inventario/fechas privadas. Expone `allowQuote`, `showPrice` y specs.

## Leads

`POST /public/leads`: `{name,phone,email?,type,message?,motorcycleId?}`. `type`: quote/availability/test_ride/contact/whatsapp. Respuesta 201: `{id}`. El servidor determina la marca/modelo desde la moto; no acepta asignación, notas ni estado del visitante. No registra automáticamente clics de WhatsApp.

`GET /admin/leads` (últimas 200) y `PATCH /admin/leads/{id}`: `{status,notes,assignedTo}`. Roles admin/sales. Estados: new/contacted/follow_up/closed/discarded. `assignedTo` es el ID de un usuario activo admin/sales, como cadena; vacío desasigna. `notes` en PATCH agrega una nota nueva; la respuesta devuelve un array de notas con note/date. No hay listado público ni eliminación de leads.

`GET /health`: verifica conexión DB sin exponer versión o credenciales.

## Promociones

Rutas exactas: `GET /public/promotions`, `GET /public/promotions/{id-o-slug}`; `GET /admin/promotions`, `GET /admin/promotions/{id-o-slug}`, `POST /admin/promotions`, `PUT /admin/promotions/{id-o-slug}`, `DELETE /admin/promotions/{id-o-slug}`. Todas las rutas admin requieren Bearer y `promotions.manage`, incluso lectura y eliminación. El esquema inicial concede ese permiso a **admin y marketing**; editor y sales reciben 403. Se evalúa el permiso guardado en la base, por lo que retirarlo revoca el acceso. POST responde 201, PUT 200 y DELETE 200 `{data:{id:"...",deleted:true}}`.

POST/PUT requieren todos los campos del siguiente ejemplo salvo brandId (opcional/null). **PUT reemplaza completamente la ficha y el conjunto de motos**: `motorcycles:[]` elimina todas las relaciones, no las motos. Omitir un campo requerido devuelve 422 sin modificar la ficha. No es PATCH. Los IDs del ejemplo son ilustrativos; hay que reemplazarlos por IDs existentes. La API no importa ofertas de ejemplo ni crea promociones por migración.

```json
{
  "title": "Campaña de prueba interna",
  "slug": "campana-prueba-interna",
  "description": "Descripción en texto plano, sin etiquetas HTML.",
  "imageUrl": "https://cdn.example.com/campana.jpg",
  "brandId": "1",
  "motorcycles": [{
    "motorcycleId": "12",
    "originalPrice": 7800000,
    "promoPrice": 7200000,
    "currency": "CRC"
  }],
  "startsAt": "2026-10-01T00:00:00-06:00",
  "endsAt": "2026-10-31T23:59:59-06:00",
  "status": "inactive",
  "featured": false,
  "showOnHome": false,
  "order": 10,
  "buttonLabel": "Ver oferta",
  "buttonHref": "/marca/modelo"
}
```

Fechas ISO 8601 con segundos y zona explícita se convierten a UTC; respuestas startsAt/endsAt terminan en Z. Fin no puede preceder inicio. `status`: active/inactive/expired; featured/showOnHome son booleanos, order entero 0..1000000. Slug único (máximo 191 bytes, letras minúsculas/números/guiones, no solo numérico); el slug de una promoción eliminada sigue reservado (409). Título máximo 255 bytes, descripción 20000, texto de botón 100, URLs 2048; máximo 100 relaciones sin duplicados. No acepta campos desconocidos ni HTML en texto.

imageUrl debe ser HTTPS sin usuario/contraseña. buttonHref permite rutas internas que empiezan con `/` o HTTPS sin credenciales; prohíbe `//`, backslash, controles y esquemas ejecutables. No descarga imágenes ni permite uploads. brandId es null o ID existente no eliminado; cuando hay marca, todas las motos relacionadas deben pertenecer a ella. Motos inexistentes o eliminadas producen 422. Borradores pueden asociarse en admin, pero nunca se exponen al público.

Cada relación contiene una única currency (CRC o USD) aplicable **a ambos precios** y coincidente con la moneda de la moto: no se admiten conversiones implícitas ni monedas separadas para original/promo. Precios JSON numéricos, no negativos, máximo 1000000000 y dos decimales. promoPrice no puede superar originalPrice. Guardado de promoción, media, relaciones y auditoría ocurre en una sola transacción; un error revierte todo.

La respuesta incluye id como cadena, campos de la ficha, `brand` (null o `{id,name,slug,primaryColor}`) y `motorcycles` como array de `{id,motorcycleId,currency,originalPrice?,promoPrice?,motorcycle}`. El id de la relación y motorcycleId también son cadenas. motorcycle contiene solo id, slug, model, version, year, showPrice, allowQuote y brand (id/name/slug/primaryColor). Admin recibe además createdAt/updatedAt y ambos precios.

Filtros públicos: active, no eliminada, `starts_at <= UTC_TIMESTAMP() <= ends_at` (extremos incluidos), marca opcional activa/no eliminada e imagen/destino seguros. Las relaciones solo incluyen motos publicadas/no eliminadas con marca/categoría activas/no eliminadas, moneda aún coincidente y marca coherente con la campaña. Si todas las relaciones dejan de ser públicas, la oferta completa desaparece (lista la omite; detalle 404). Una campaña general creada con motorcycles vacío puede seguir visible. Si showPrice=false, se omiten originalPrice/promoPrice de esa relación. No se devuelven SKU, inventario, autores, auditoría, deleted_at ni detalles administrativos. Orden ascendente por order y luego ID; showOnHome/featured no alteran la vigencia ni se fuerzan automáticamente.

Eliminación es lógica (deleted_at); no borra motos, medios o relaciones históricas. No hay restauración automática. Lecturas de promociones: 300 por IP cada 15 minutos; escrituras autorizadas: 60 por IP cada 15 minutos. Al exceder: 429 JSON, Retry-After: 900, expuesto por CORS. Permanecen X-Request-ID y errores 401/403/404/409/422/429. Los orígenes de web/admin configurados siguen siendo exactos.

Instalación: aplicar **003_promotions.sql una sola vez**, después de las migraciones anteriores, consultando schema_migrations y haciendo respaldo. Agrega button_label/button_href; no borra ni publica datos existentes. El dominio definitivo será https://api.motoapexcr.com/v1; cambiar la base de frontends y recompilar no cambia este contrato.

## Contenido web (migración 004)

Todas las rutas siguientes usan `/v1`, `{data:...}` o `{error:...}` y `X-Request-ID`. No publican contenido de ejemplo. Contacto, horarios, logo, favicon y redes se administran aquí, nunca en `settings`.

| Recurso | Lectura pública | Administración |
|---|---|---|
| Páginas | GET /public/pages, GET /public/pages/{id-o-slug} | GET/POST /admin/pages; GET/PUT/DELETE /admin/pages/{id-o-slug} |
| Banners | GET /public/banners, GET /public/banners/{id} | GET/POST /admin/banners; GET/PUT/DELETE /admin/banners/{id} |
| Redes | GET /public/social-links, GET /public/social-links/{id} | GET/POST /admin/social-links; GET/PUT/DELETE /admin/social-links/{id} |
| Contacto único | GET /public/contact | GET/PUT /admin/contact |

Administración requiere Bearer y `content.manage`: roles iniciales admin, marketing y editor. Sales recibe 403. Sin sesión: 401. Conflicto de slug: 409; validación: 422; límites: 429 con Retry-After expuesto por CORS. Límite de lectura 300 y escritura 80 por IP/15 minutos. POST devuelve 201, PUT/DELETE 200. DELETE conserva la fila y las revisiones; el slug permanece reservado. Contacto no admite POST ni DELETE. No hay rutas públicas de revisiones o autores.

PUT reemplaza todos los campos editables del recurso. Se requieren todas las claves del ejemplo, incluso valores null o vacíos; pageId es opcional y omitirlo elimina la relación. Campos desconocidos se rechazan. Se guardan una revisión anterior y posterior a cada edición, con autor privado, y una revisión al eliminar. Escrituras, medios, revisiones y auditoría se confirman en una transacción.

Banner compatible con HERO_SLIDES (ejemplo ilustrativo **inactivo**, no se importa):

```json
{"title":"Título del carrusel","subtitle":"Texto plano","imageUrl":"https://images.example.com/desktop.jpg","mobileImageUrl":"https://images.example.com/mobile.jpg","alt":"Descripción de la imagen","brandId":null,"accentColor":"#CC2233","ctaPrimary":{"label":"Ver catálogo","href":"/catalogo"},"ctaSecondary":{"label":"Consultar","href":"/contacto"},"placement":"home_hero","order":1,"status":"inactive","startsAt":null,"endsAt":null,"pageId":null}
```

La respuesta añade id como cadena, brandSlug y brand `{id,name,slug,primaryColor}` o null. `imageUrl`, `mobileImageUrl`, `ctaPrimary`, `ctaSecondary`, `brandSlug`, `accentColor`, title y subtitle pueden mapearse directamente a HERO_SLIDES. Un botón ausente se envía como null; un botón presente exige label y href. `mobileImageUrl` vacío permite fallback a escritorio. GET público admite `?placement=home_hero` y ordena por order e id. Solo banners activos y vigentes según UTC de MySQL; límites opcionales inclusivos. Una marca inactiva o página relacionada no publicada oculta el banner. Sin marca ni página se permite un banner general.

Página:

```json
{"title":"Acerca de","slug":"acerca-de","content":"Contenido sin HTML","contentFormat":"text","order":2,"status":"draft","seo":{"title":"Acerca de MotoApex","description":"Descripción para buscadores"}}
```

Para `contentFormat:blocks`, content es una lista de hasta 100 bloques: `{type:heading,text,level:1..6}`, `{type:paragraph,text}`, `{type:image,url,alt}`, `{type:link,text,href}`. No se admiten HTML, scripts, estilos ni claves arbitrarias. Máximo contenido 100000 bytes; título 255, SEO título 255/descripción 500. Estados draft, published, hidden y archived; público devuelve solamente published, sin fechas administrativas, revisiones ni updatedBy. El frontend debe renderizar texto como texto, nunca innerHTML.

Contacto (configuración singleton existente site_key=main):

```json
{"businessName":"Nombre comercial","phone":"+506 2222-2222","whatsapp":"+506 8888-8888","email":"contacto@example.com","address":"Dirección","latitude":9.998,"longitude":-84.116,"hours":[{"day":1,"closed":false,"opens":"08:00","closes":"17:00"},{"day":7,"closed":true,"opens":null,"closes":null}],"logoUrl":"https://images.example.com/logo.png","faviconUrl":"https://images.example.com/favicon.png"}
```

day: 1=lunes..7=domingo, sin duplicados. Horarios HH:MM, cierre posterior a apertura el mismo día; closed requiere horas null. Coordenadas juntas o ambas null, latitud ±90/longitud ±180. Teléfono/WhatsApp admiten dígitos, +, espacios, paréntesis y guiones. Campos opcionales en sentido de contenido se vacían con string vacío o lista vacía, conservando su clave.

Red social:

```json
{"platform":"instagram","label":"Instagram","url":"https://www.instagram.com/example","order":1,"status":"inactive"}
```

Plataformas facebook, instagram, tiktok, youtube, x, linkedin, whatsapp, other. Público solo active, orden por order/id. Imágenes y redes exigen HTTPS sin credenciales; botones y enlaces de bloque admiten una ruta interna `/...` o HTTPS, nunca `//`, backslash, controles, javascript:, data: u otros esquemas. Color #RRGGBB. Fechas ISO 8601 con segundos y zona; relaciones se comprueban en el servidor. La API almacena enlaces, no descarga imágenes.

Instalación: aplicar 004_web_content.sql una sola vez después de 003. No reimportar schema.sql ni publicar contenido del frontend automáticamente. Las rutas serán idénticas al migrar la base a https://api.motoapexcr.com/v1; actualizar configuración de frontends y orígenes del servidor de forma coordinada.

## Usuarios, Configuración, Cuenta y MFA (migración 005)

El contrato siguiente reemplaza el bloqueo provisional de MFA/cambio obligatorio de la base inicial. Todas las rutas tienen prefijo `/v1`. IDs como cadenas; envoltorios `{data:...}`/`{error:{code,message}}`, X-Request-ID y errores 401, 403, 409, 422, 429. SMTP/cifrado pendientes: 503 explícito. Bearer, challengeToken y reauthToken deben mantenerse únicamente en memoria. Nunca son intercambiables: un challengeToken no autoriza rutas admin.

| Método y ruta | Permiso / propósito |
|---|---|
| GET /admin/users | users.manage, lista sin eliminados |
| GET /admin/users/{id} | users.manage, detalle |
| POST /admin/users | users.manage + reautenticación users.manage |
| PUT /admin/users/{id} | users.manage + reautenticación users.manage |
| DELETE /admin/users/{id} | users.manage + reautenticación users.manage, eliminación lógica |
| GET /admin/roles | users.manage, roles y permisos de referencia; sin edición |
| GET /admin/lead-assignees | leads.manage, exclusivamente id y name de usuarios activos admin/sales |
| GET /admin/settings | settings.manage, solo claves permitidas |
| PUT /admin/settings/{key} | settings.manage + reautenticación settings.manage |
| GET /public/settings | ajustes públicos de la lista explícita |
| GET /auth/me, GET /auth/profile | perfil propio, sesión completa |
| PUT /auth/profile | sesión completa + reautenticación profile.edit |
| POST /auth/login | contraseña, después MFA/cambio obligatorio si corresponde |
| POST /auth/logout | revoca sesión actual |
| POST /auth/reauth | contraseña actual y MFA si está activo; permiso sensible sigue comprobándose en la operación |
| POST /auth/password/change | sesión completa + reautenticación password.change + contraseña actual |
| POST /auth/password/required | challengeToken limitado de cambio obligatorio |
| POST /auth/password/forgot | solicitud genérica de recuperación SMTP |
| POST /auth/password/reset | enlace de recuperación, no emite sesión |
| GET /auth/mfa/status | estado enabled, sesión completa |
| POST /auth/mfa/enroll | sesión completa + reautenticación mfa.manage |
| POST /auth/mfa/confirm | sesión completa + reautenticación mfa.manage y código de alta |
| POST /auth/mfa/challenge | challengeToken limitado de login y code o recoveryCode |
| POST /auth/mfa/disable | sesión completa + reautenticación mfa.manage |
| POST /auth/mfa/recovery-codes | sesión completa + reautenticación mfa.manage, rota códigos |

Roles iniciales: users.manage/settings.manage únicamente admin; leads.manage admin/sales. No se permite escalada mediante role_id, permisos, isAdmin u otros campos arbitrarios. El rol se resuelve exclusivamente por code existente en roles; un rol delegado con users.manage no puede asignar admin ni un rol con permisos que no posee. roles.manage no habilita una ruta de edición en esta versión. Se rechazan modificaciones que desactiven, eliminen o cambien el rol del último administrador activo, con 409 LAST_ADMIN y bloqueo transaccional para serializar cambios concurrentes. No hay borrado físico de usuarios.

Creación de usuario (POST, requiere contraseña explícita; ejemplo no se importa):

```json
{"name":"Persona","email":"persona@example.com","phone":"+506 8888-8888","avatarUrl":"https://images.example.com/avatar.jpg","role":"sales","status":"active","password":"CONTRASEÑA_PROPIA_DE_16_A_72_BYTES"}
```

PUT reemplaza name, email, phone, avatarUrl, role y status, requeridos incluso si teléfono/avatar están vacíos. No requiere password; `newPassword` opcional impone un cambio obligatorio y revoca sesiones. `mustChangePassword:true` opcional permite imponer ese cambio; false se rechaza porque solo el usuario puede completarlo. Correo único normalizado a minúsculas, conflicto 409 incluso si pertenece a una cuenta eliminada. Respuestas de usuario: `{id,name,email,phone,avatarUrl,role,status,lastAccess,mustChangePassword}`. No devuelven hashes, secretos MFA, tokens de sesiones ni códigos de recuperación. Desactivación, eliminación, cambio de rol/correo/contraseña o imposición de cambio obligatorio revocan sesiones y desafíos y anulan enlaces de recuperación. La huella de permisos de cada sesión detecta cambios de role_permissions al siguiente acceso y revoca todas las sesiones de esa cuenta. La migración 005 revoca sesiones antiguas sin huella.

Perfil propio PUT acepta exclusivamente name, email, phone y avatarUrl. No acepta estado, rol, contraseña ni permisos. Cambio de correo requiere reautenticación y revoca sesiones. La contraseña cambia mediante su ruta separada.

Ajustes permitidos (PUT body `{ "value": "USD" }`):

| key | Validación | Público |
|---|---|---|
| site_url | HTTPS sin credenciales | sí |
| admin_url | HTTPS sin credenciales | no |
| api_url | HTTPS sin credenciales | no |
| timezone | identificador IANA reconocido por PHP | sí |
| default_currency | CRC o USD | sí |

GET settings devuelve una lista `{id,key,value,valueType:"string",public:boolean}`. La visibilidad se define en código, no por un flag enviado por cliente o por registros arbitrarios de settings. No admite nuevas claves, secretos, credenciales MySQL, SMTP, CORS o variables del servidor. Estos URLs informativos no reconfiguran el servidor. Contacto, horarios, logos, favicon y redes usan exclusivamente Contenido web, evitando duplicación.

### Login y cambio obligatorio

POST /auth/login `{email,password}` conserva, para cuentas sin requisitos pendientes, `{data:{token,expiresAt,user}}`. Si MFA está activo devuelve `{data:{challenge:"mfa_login",challengeToken,expiresAt}}`, sin token de sesión ni datos privados. Si solo requiere cambio de contraseña devuelve `{data:{challenge:"password_change",challengeToken,expiresAt}}`. Desafíos vencen a los 5 minutos, se almacenan como hash, se vinculan a contraseña y permisos vigentes y son de un solo uso. No conceden acceso general.

Para mfa_login: POST /auth/mfa/challenge `{challengeToken,code:"123456"}` o `{challengeToken,recoveryCode:"..."}`. Nunca enviar ambos. Respuesta de éxito: sesión completa, o desafío password_change si aún debe cambiar contraseña. Un código incorrecto consume ese desafío: volver al login, sujeto a límites. Para password_change: POST /auth/password/required `{challengeToken,newPassword}`. Requiere nueva contraseña de 16–72 bytes, diferente de la anterior. Completa must_change_password, revoca sesiones/enlaces previos y emite una sesión completa; si MFA está activo, el desafío debe proceder del MFA ya validado. No hay modo de evitar MFA mediante cambio obligatorio.

POST /auth/password/change `{currentPassword,newPassword}` exige contraseña actual y X-Reauth-Token válido para password.change. Devuelve `{data:{changed:true,loginRequired:true}}`, revocando todas las sesiones. Volver al login. Recuperación por correo mantiene MFA habilitado y también obliga a pasar MFA en el siguiente login.

### Reautenticación sensible

POST /auth/reauth `{password,action}` donde action es users.manage, settings.manage, profile.edit, password.change o mfa.manage. Si MFA está activo añadir `code` o `recoveryCode`. Devuelve `{data:{reauthToken,challenge:"reauth",expiresAt}}`. Enviar ese token en `X-Reauth-Token` junto al Bearer en una única operación del propósito solicitado. CORS permite este encabezado. Vence a los 5 minutos y está vinculado al usuario y a la sesión actual. No concede permisos por sí mismo; la operación comprueba su permiso en el servidor. No reutilizar después de un éxito. Validaciones/transacciones fallidas no consumen un token confirmado únicamente dentro de la transacción que fue revertida.

### Recuperación SMTP

POST /auth/password/forgot `{email}` devuelve siempre el mismo cuerpo para correos activos/inexistentes/inactivos: `{data:{message:"Si existe una cuenta activa, recibirás un enlace de recuperación."}}`. No devuelve el token. Un correo activo recibe un enlace HTTPS con fragmento `#token=...`, válido 30 minutos, con token de 256 bits almacenado solo como SHA-256. Una solicitud nueva invalida enlaces anteriores. POST /auth/password/reset `{token,newPassword}` verifica vencimiento/un solo uso/estado activo, cambia contraseña y revoca sesiones/desafíos/enlaces. Devuelve `{data:{changed:true,loginRequired:true}}`; un enlace inválido, usado o vencido responde 401 INVALID_RESET. No crea sesión ni elimina MFA.

Sin SMTP correctamente configurado todas las solicitudes reciben 503 SMTP_NOT_CONFIGURED. No se simula entrega. SMTP y URL de recuperación se configuran únicamente en config.local.php del servidor; instalación, TLS, remitente y pruebas reales pendientes se detallan en docs/accounts-install.md. El mensaje SMTP nunca se registra en logs. El frontend debe leer/eliminar el fragmento, conservar token en memoria y no enviarlo a analytics.

### MFA TOTP

1. Reautenticar con action mfa.manage y llamar POST /auth/mfa/enroll. Devuelve solamente `otpauthUri` para configurar el autenticador. Es la única respuesta de aprovisionamiento que contiene el secreto, protegida por sesión y contraseña reciente: mostrar QR local, no enviarlo a servicios externos, logs o almacenamiento persistente. Los GET de perfil/usuarios nunca exponen ese secreto.
2. Reautenticar de nuevo y POST /auth/mfa/confirm `{code}`. El secreto se almacena cifrado con Sodium secretbox y clave de 32 bytes externa a MySQL. TOTP RFC 6238 SHA-1, 6 dígitos/30 segundos, ventana ±1 paso y contador antirrepetición. La confirmación activa MFA, revoca sesiones y entrega 10 recoveryCodes aleatorios una sola vez; mostrar para que el usuario los guarde de forma privada y volver al login.
3. Login entrega mfa_login; superarlo con un código o código de recuperación. Cada recoveryCode se almacena solo como hash y se consume una vez. Los códigos TOTP ya usados no pueden reutilizarse, incluso para reautenticar en el mismo intervalo: esperar al próximo código o usar uno de recuperación.
4. POST /auth/mfa/recovery-codes, con reautenticación MFA, invalida códigos anteriores y entrega nuevos una vez. POST /auth/mfa/disable exige reautenticación de contraseña + MFA/recuperación, elimina secreto/códigos y revoca todas las sesiones. Devuelve enabled:false y loginRequired:true.

No se emite sesión completa antes de MFA. Login de cuentas sin MFA permanece compatible. Secretos MFA anteriores que no estén cifrados en este formato deben recuperarse mediante mantenimiento privado autorizado; no hay fallback a texto plano ni bypass. Falta de Sodium/clave produce 503 MFA_NOT_CONFIGURED en las operaciones que requieren cifrado.

Límites por IP/15 minutos: login 10, MFA login/confirm 10, recuperación 5, reset/cambio obligatorio/cambio propio 10, reauth 10, alta/desactivación/rotación MFA 5, usuarios lectura 150/escritura 40, ajustes lectura 150/escritura 30. Además buckets independientes de IP: login por correo 8, MFA login por cuenta 8, recuperación por correo 3 y reauth por cuenta 8. Bloqueo temporal del usuario después de fallos de contraseña. 429 incluye Retry-After:900 expuesto por CORS. Auditoría registra acciones y actor/request ID, nunca contraseñas, tokens, secretos o cuerpos de correo. Instalación final y SMTP real en Hostinger necesitan validación posterior autorizada.
