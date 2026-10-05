# Contrato API v1

Base: `https://darksalmon-quetzal-730302.hostingersite.com/v1`. Solicitudes con cuerpo: `Content-Type: application/json`. Respuestas: `{ "data": ... }` o `{ "error": { "code": "...", "message": "..." } }`. Los errores son 400/401/403/404/405/409/413/415/422/429/500. El encabezado `X-Request-ID` permite localizar errores sin publicar detalles internos.

## AutenticaciÃ³n

- `POST /auth/login`: `{ "email": "...", "password": "..." }`; devuelve `{token, expiresAt, user}`. `user` usa campos DB: `id`, `name`, `email`, `role`, `status`, `lastAccess`.
- `GET /auth/me`: usuario de la sesiÃ³n.
- `POST /auth/logout`: cuerpo `{}`; revoca el token actual.

Todas las rutas privadas usan `Authorization: Bearer TOKEN`. MantÃ©n el token en memoria; al recargar se requiere iniciar sesiÃ³n. No guardarlo en localStorage, URLs ni logs. Si se necesita sesiÃ³n persistente, implementar posteriormente cookies HttpOnly, Secure y protecciÃ³n CSRF segÃºn los dominios reales. No insertar contenido API con `dangerouslySetInnerHTML`.

## CatÃ¡logo

`GET /public/brands`, `/public/categories`, `/public/motorcycles` y sus variantes `/{id-o-slug}`. Solo se publican marcas/categorÃ­as activas y motos con `published=true` cuyo brand/category siga activo. Los listados devuelven arrays. Esta versiÃ³n no tiene bÃºsqueda ni paginaciÃ³n; usar filtros del frontend para catÃ¡logos pequeÃ±os.

Admin: `GET /admin/{brands|categories|motorcycles}`, `GET /admin/{kind}/{id-o-slug}`, `POST /admin/{kind}`, `PUT /admin/{kind}/{id-o-slug}` (reemplazo completo), `DELETE /admin/{kind}/{id-o-slug}`.

`admin` y `editor` crean/editan; `marketing` consulta. Publicar o retirar publicaciÃ³n requiere motorcycles.publish (por defecto solo admin). TambiÃ©n se comprueban los permisos de role_permissions. Solo `admin` elimina. Las marcas/categorÃ­as en uso no se eliminan; desactivarlas. La API devuelve IDs numÃ©ricos del esquema MySQL como cadenas y fechas UTC. Slugs Ãºnicos por tipo. Referenciar IDs devueltos por la API, nunca los IDs mock del otro proyecto.

Marca: `name`, `slug` requeridos; `description`, `status` (active/inactive, defecto inactive), `order` entero, `primaryColor`, `secondaryColor`, `accentLight` (#RRGGBB), `logo`, `heroImageUrl`, `tileImageUrl` (HTTPS o vacÃ­o), `tagline`, `slogan`. La respuesta pÃºblica agrega `categories` de esa marca y categorÃ­as generales.

CategorÃ­a: `name`, `slug`, `description`, `status`, `order`, `brandId` opcional (vacÃ­o = general).

Moto, ejemplo mÃ­nimo para crear borrador:

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

Otros campos: `version`, `sku`, `tagline`, `shortDescription`, `description`, `displacement`, `inventory` entero no negativo, `hp`, `promoPrice` nÃºmero o null, `status` available/reserved/sold_out/coming_soon, `featured`, `isNew`, `specs` [{group,label,value}], `colors` [{id,name,hex,status,available,order,images:[{id,url,alt,label,order,isPrimary}]}]. IDs de colores/imÃ¡genes: cadenas Ãºnicas generadas por el frontend. MÃ¡ximo 30 colores, 30 imÃ¡genes por color, 100 especificaciones. Orden numÃ©rico; una sola imagen principal por color. Cuerpo mÃ¡ximo 256 KiB. Los booleanos deben ser booleanos JSON, los nÃºmeros deben ser nÃºmeros.

Respuesta privada aÃ±ade `brand`, `category`, `id`, `createdAt`, `updatedAt`. Respuesta pÃºblica transforma `featuredâ†’isFeatured`, `displacementâ†’cc`, `colorsâ†’colorOptions`, incorpora `brandName`, `brandColor`, `categoryName`; `availability` usa available/reserved/coming-soon/sold-out. **reserved no equivale a pre-order**: agregar ese estado al tipo web. Solo incluye colores activos. `price` y `promoPrice` se omiten cuando `showPrice=false`; no publica SKU/inventario/fechas privadas. Expone `allowQuote`, `showPrice` y specs.

## Leads

`POST /public/leads`: `{name,phone,email?,type,message?,motorcycleId?}`. `type`: quote/availability/test_ride/contact/whatsapp. Respuesta 201: `{id}`. El servidor determina la marca/modelo desde la moto; no acepta asignaciÃ³n, notas ni estado del visitante. No registra automÃ¡ticamente clics de WhatsApp.

`GET /admin/leads` (Ãºltimas 200) y `PATCH /admin/leads/{id}`: `{status,notes,assignedTo}`. Roles admin/sales. Estados: new/contacted/follow_up/closed/discarded. `assignedTo` es el ID de un usuario activo admin/sales, como cadena; vacÃ­o desasigna. `notes` en PATCH agrega una nota nueva; la respuesta devuelve un array de notas con note/date. No hay listado pÃºblico ni eliminaciÃ³n de leads.

`GET /health`: verifica conexiÃ³n DB sin exponer versiÃ³n o credenciales.

## Promociones

Rutas exactas: `GET /public/promotions`, `GET /public/promotions/{id-o-slug}`; `GET /admin/promotions`, `GET /admin/promotions/{id-o-slug}`, `POST /admin/promotions`, `PUT /admin/promotions/{id-o-slug}`, `DELETE /admin/promotions/{id-o-slug}`. Todas las rutas admin requieren Bearer y `promotions.manage`, incluso lectura y eliminaciÃ³n. El esquema inicial concede ese permiso a **admin y marketing**; editor y sales reciben 403. Se evalÃºa el permiso guardado en la base, por lo que retirarlo revoca el acceso. POST responde 201, PUT 200 y DELETE 200 `{data:{id:"...",deleted:true}}`.

POST/PUT requieren todos los campos del siguiente ejemplo salvo brandId (opcional/null). **PUT reemplaza completamente la ficha y el conjunto de motos**: `motorcycles:[]` elimina todas las relaciones, no las motos. Omitir un campo requerido devuelve 422 sin modificar la ficha. No es PATCH. Los IDs del ejemplo son ilustrativos; hay que reemplazarlos por IDs existentes. La API no importa ofertas de ejemplo ni crea promociones por migraciÃ³n.

```json
{
  "title": "CampaÃ±a de prueba interna",
  "slug": "campana-prueba-interna",
  "description": "DescripciÃ³n en texto plano, sin etiquetas HTML.",
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

Fechas ISO 8601 con segundos y zona explÃ­cita se convierten a UTC; respuestas startsAt/endsAt terminan en Z. Fin no puede preceder inicio. `status`: active/inactive/expired; featured/showOnHome son booleanos, order entero 0..1000000. Slug Ãºnico (mÃ¡ximo 191 bytes, letras minÃºsculas/nÃºmeros/guiones, no solo numÃ©rico); el slug de una promociÃ³n eliminada sigue reservado (409). TÃ­tulo mÃ¡ximo 255 bytes, descripciÃ³n 20000, texto de botÃ³n 100, URLs 2048; mÃ¡ximo 100 relaciones sin duplicados. No acepta campos desconocidos ni HTML en texto.

imageUrl debe ser HTTPS sin usuario/contraseÃ±a. buttonHref permite rutas internas que empiezan con `/` o HTTPS sin credenciales; prohÃ­be `//`, backslash, controles y esquemas ejecutables. No descarga imÃ¡genes ni permite uploads. brandId es null o ID existente no eliminado; cuando hay marca, todas las motos relacionadas deben pertenecer a ella. Motos inexistentes o eliminadas producen 422. Borradores pueden asociarse en admin, pero nunca se exponen al pÃºblico.

Cada relaciÃ³n contiene una Ãºnica currency (CRC o USD) aplicable **a ambos precios** y coincidente con la moneda de la moto: no se admiten conversiones implÃ­citas ni monedas separadas para original/promo. Precios JSON numÃ©ricos, no negativos, mÃ¡ximo 1000000000 y dos decimales. promoPrice no puede superar originalPrice. Guardado de promociÃ³n, media, relaciones y auditorÃ­a ocurre en una sola transacciÃ³n; un error revierte todo.

La respuesta incluye id como cadena, campos de la ficha, `brand` (null o `{id,name,slug,primaryColor}`) y `motorcycles` como array de `{id,motorcycleId,currency,originalPrice?,promoPrice?,motorcycle}`. El id de la relaciÃ³n y motorcycleId tambiÃ©n son cadenas. motorcycle contiene solo id, slug, model, version, year, showPrice, allowQuote y brand (id/name/slug/primaryColor). Admin recibe ademÃ¡s createdAt/updatedAt y ambos precios.

Filtros pÃºblicos: active, no eliminada, `starts_at <= UTC_TIMESTAMP() <= ends_at` (extremos incluidos), marca opcional activa/no eliminada e imagen/destino seguros. Las relaciones solo incluyen motos publicadas/no eliminadas con marca/categorÃ­a activas/no eliminadas, moneda aÃºn coincidente y marca coherente con la campaÃ±a. Si todas las relaciones dejan de ser pÃºblicas, la oferta completa desaparece (lista la omite; detalle 404). Una campaÃ±a general creada con motorcycles vacÃ­o puede seguir visible. Si showPrice=false, se omiten originalPrice/promoPrice de esa relaciÃ³n. No se devuelven SKU, inventario, autores, auditorÃ­a, deleted_at ni detalles administrativos. Orden ascendente por order y luego ID; showOnHome/featured no alteran la vigencia ni se fuerzan automÃ¡ticamente.

EliminaciÃ³n es lÃ³gica (deleted_at); no borra motos, medios o relaciones histÃ³ricas. No hay restauraciÃ³n automÃ¡tica. Lecturas de promociones: 300 por IP cada 15 minutos; escrituras autorizadas: 60 por IP cada 15 minutos. Al exceder: 429 JSON, Retry-After: 900, expuesto por CORS. Permanecen X-Request-ID y errores 401/403/404/409/422/429. Los orÃ­genes de web/admin configurados siguen siendo exactos.

InstalaciÃ³n: aplicar **003_promotions.sql una sola vez**, despuÃ©s de las migraciones anteriores, consultando schema_migrations y haciendo respaldo. Agrega button_label/button_href; no borra ni publica datos existentes. El dominio definitivo serÃ¡ https://api.motoapexcr.com/v1; cambiar la base de frontends y recompilar no cambia este contrato.

## Contenido web (migraciÃ³n 004)

Todas las rutas siguientes usan `/v1`, `{data:...}` o `{error:...}` y `X-Request-ID`. No publican contenido de ejemplo. Contacto, horarios, logo, favicon y redes se administran aquÃ­, nunca en `settings`.

| Recurso | Lectura pÃºblica | AdministraciÃ³n |
|---|---|---|
| PÃ¡ginas | GET /public/pages, GET /public/pages/{id-o-slug} | GET/POST /admin/pages; GET/PUT/DELETE /admin/pages/{id-o-slug} |
| Banners | GET /public/banners, GET /public/banners/{id} | GET/POST /admin/banners; GET/PUT/DELETE /admin/banners/{id} |
| Redes | GET /public/social-links, GET /public/social-links/{id} | GET/POST /admin/social-links; GET/PUT/DELETE /admin/social-links/{id} |
| Contacto Ãºnico | GET /public/contact | GET/PUT /admin/contact |

AdministraciÃ³n requiere Bearer y `content.manage`: roles iniciales admin, marketing y editor. Sales recibe 403. Sin sesiÃ³n: 401. Conflicto de slug: 409; validaciÃ³n: 422; lÃ­mites: 429 con Retry-After expuesto por CORS. LÃ­mite de lectura 300 y escritura 80 por IP/15 minutos. POST devuelve 201, PUT/DELETE 200. DELETE conserva la fila y las revisiones; el slug permanece reservado. Contacto no admite POST ni DELETE. No hay rutas pÃºblicas de revisiones o autores.

PUT reemplaza todos los campos editables del recurso. Se requieren todas las claves del ejemplo, incluso valores null o vacÃ­os; pageId es opcional y omitirlo elimina la relaciÃ³n. Campos desconocidos se rechazan. Se guardan una revisiÃ³n anterior y posterior a cada ediciÃ³n, con autor privado, y una revisiÃ³n al eliminar. Escrituras, medios, revisiones y auditorÃ­a se confirman en una transacciÃ³n.

Banner compatible con HERO_SLIDES (ejemplo ilustrativo **inactivo**, no se importa):

```json
{"title":"TÃ­tulo del carrusel","subtitle":"Texto plano","imageUrl":"https://images.example.com/desktop.jpg","mobileImageUrl":"https://images.example.com/mobile.jpg","alt":"DescripciÃ³n de la imagen","brandId":null,"accentColor":"#CC2233","ctaPrimary":{"text":"Ver catÃ¡logo","href":"/catalogo"},"ctaSecondary":{"text":"Consultar","href":"/contacto"},"placement":"home_hero","order":1,"status":"inactive","startsAt":null,"endsAt":null,"pageId":null}
```

La respuesta aÃ±ade id como cadena, brandSlug y brand `{id,name,slug,primaryColor}` o null. `imageUrl`, `mobileImageUrl`, `ctaPrimary`, `ctaSecondary`, `brandSlug`, `accentColor`, title y subtitle pueden mapearse directamente a HERO_SLIDES. Un botÃ³n ausente se envÃ­a como null; un botÃ³n presente exige text y href. `mobileImageUrl` vacÃ­o permite fallback a escritorio. GET pÃºblico admite `?placement=home_hero` y ordena por order e id. Solo banners activos y vigentes segÃºn UTC de MySQL; lÃ­mites opcionales inclusivos. Una marca inactiva o pÃ¡gina relacionada no publicada oculta el banner. Sin marca ni pÃ¡gina se permite un banner general.

PÃ¡gina:

```json
{"title":"Acerca de","slug":"acerca-de","content":"Contenido sin HTML","contentFormat":"text","order":2,"status":"draft","seo":{"title":"Acerca de MotoApex","description":"DescripciÃ³n para buscadores"}}
```

Para `contentFormat:blocks`, content es una lista de hasta 100 bloques: `{type:heading,text,level:1..6}`, `{type:paragraph,text}`, `{type:image,url,alt}`, `{type:link,text,href}`. No se admiten HTML, scripts, estilos ni claves arbitrarias. MÃ¡ximo contenido 100000 bytes; tÃ­tulo 255, SEO tÃ­tulo 255/descripciÃ³n 500. Estados draft, published, hidden y archived; pÃºblico devuelve solamente published, sin fechas administrativas, revisiones ni updatedBy. El frontend debe renderizar texto como texto, nunca innerHTML.

Contacto (configuraciÃ³n singleton existente site_key=main):

```json
{"businessName":"Nombre comercial","phone":"+506 2222-2222","whatsapp":"+506 8888-8888","email":"contacto@example.com","address":"DirecciÃ³n","latitude":9.998,"longitude":-84.116,"hours":[{"day":1,"closed":false,"opens":"08:00","closes":"17:00"},{"day":7,"closed":true,"opens":null,"closes":null}],"logoUrl":"https://images.example.com/logo.png","faviconUrl":"https://images.example.com/favicon.png"}
```

day: 1=lunes..7=domingo, sin duplicados. Horarios HH:MM, cierre posterior a apertura el mismo dÃ­a; closed requiere horas null. Coordenadas juntas o ambas null, latitud Â±90/longitud Â±180. TelÃ©fono/WhatsApp admiten dÃ­gitos, +, espacios, parÃ©ntesis y guiones. Campos opcionales en sentido de contenido se vacÃ­an con string vacÃ­o o lista vacÃ­a, conservando su clave.

Red social:

```json
{"platform":"instagram","label":"Instagram","url":"https://www.instagram.com/example","order":1,"status":"inactive"}
```

Plataformas facebook, instagram, tiktok, youtube, x, linkedin, whatsapp, other. PÃºblico solo active, orden por order/id. ImÃ¡genes y redes exigen HTTPS sin credenciales; botones y enlaces de bloque admiten una ruta interna `/...` o HTTPS, nunca `//`, backslash, controles, javascript:, data: u otros esquemas. Color #RRGGBB. Fechas ISO 8601 con segundos y zona; relaciones se comprueban en el servidor. La API almacena enlaces, no descarga imÃ¡genes.

InstalaciÃ³n: aplicar 004_web_content.sql una sola vez despuÃ©s de 003. No reimportar schema.sql ni publicar contenido del frontend automÃ¡ticamente. Las rutas serÃ¡n idÃ©nticas al migrar la base a https://api.motoapexcr.com/v1; actualizar configuraciÃ³n de frontends y orÃ­genes del servidor de forma coordinada.

## Usuarios, ConfiguraciÃ³n, Cuenta y MFA (migraciÃ³n 005)

El contrato siguiente reemplaza el bloqueo provisional de MFA/cambio obligatorio de la base inicial. Todas las rutas tienen prefijo `/v1`. IDs como cadenas; envoltorios `{data:...}`/`{error:{code,message}}`, X-Request-ID y errores 401, 403, 409, 422, 429. SMTP/cifrado pendientes: 503 explÃ­cito. Bearer, challengeToken y reauthToken deben mantenerse Ãºnicamente en memoria. Nunca son intercambiables: un challengeToken no autoriza rutas admin.

| MÃ©todo y ruta | Permiso / propÃ³sito |
|---|---|
| GET /admin/users | users.manage, lista sin eliminados |
| GET /admin/users/{id} | users.manage, detalle |
| POST /admin/users | users.manage + reautenticaciÃ³n users.manage |
| PUT /admin/users/{id} | users.manage + reautenticaciÃ³n users.manage |
| DELETE /admin/users/{id} | users.manage + reautenticaciÃ³n users.manage, eliminaciÃ³n lÃ³gica |
| GET /admin/roles | users.manage, roles y permisos de referencia; sin ediciÃ³n |
| GET /admin/lead-assignees | leads.manage, exclusivamente id y name de usuarios activos admin/sales |
| GET /admin/settings | settings.manage, solo claves permitidas |
| PUT /admin/settings/{key} | settings.manage + reautenticaciÃ³n settings.manage |
| GET /public/settings | ajustes pÃºblicos de la lista explÃ­cita |
| GET /auth/me, GET /auth/profile | perfil propio, sesiÃ³n completa |
| PUT /auth/profile | sesiÃ³n completa + reautenticaciÃ³n profile.edit |
| POST /auth/login | contraseÃ±a, despuÃ©s MFA/cambio obligatorio si corresponde |
| POST /auth/logout | revoca sesiÃ³n actual |
| POST /auth/reauth | contraseÃ±a actual y MFA si estÃ¡ activo; permiso sensible sigue comprobÃ¡ndose en la operaciÃ³n |
| POST /auth/password/change | sesiÃ³n completa + reautenticaciÃ³n password.change + contraseÃ±a actual |
| POST /auth/password/required | challengeToken limitado de cambio obligatorio |
| POST /auth/password/forgot | solicitud genÃ©rica de recuperaciÃ³n SMTP |
| POST /auth/password/reset | enlace de recuperaciÃ³n, no emite sesiÃ³n |
| GET /auth/mfa/status | estado enabled, sesiÃ³n completa |
| POST /auth/mfa/enroll | sesiÃ³n completa + reautenticaciÃ³n mfa.manage |
| POST /auth/mfa/confirm | sesiÃ³n completa + reautenticaciÃ³n mfa.manage y cÃ³digo de alta |
| POST /auth/mfa/challenge | challengeToken limitado de login y code o recoveryCode |
| POST /auth/mfa/disable | sesiÃ³n completa + reautenticaciÃ³n mfa.manage |
| POST /auth/mfa/recovery-codes | sesiÃ³n completa + reautenticaciÃ³n mfa.manage, rota cÃ³digos |

Roles iniciales: users.manage/settings.manage Ãºnicamente admin; leads.manage admin/sales. No se permite escalada mediante role_id, permisos, isAdmin u otros campos arbitrarios. El rol se resuelve exclusivamente por code existente en roles; un rol delegado con users.manage no puede asignar admin ni un rol con permisos que no posee. roles.manage no habilita una ruta de ediciÃ³n en esta versiÃ³n. Se rechazan modificaciones que desactiven, eliminen o cambien el rol del Ãºltimo administrador activo, con 409 LAST_ADMIN y bloqueo transaccional para serializar cambios concurrentes. No hay borrado fÃ­sico de usuarios.

CreaciÃ³n de usuario (POST, requiere contraseÃ±a explÃ­cita; ejemplo no se importa):

```json
{"name":"Persona","email":"persona@example.com","phone":"+506 8888-8888","avatarUrl":"https://images.example.com/avatar.jpg","role":"sales","status":"active","password":"CONTRASEÃ‘A_PROPIA_DE_16_A_72_BYTES"}
```

PUT reemplaza name, email, phone, avatarUrl, role y status, requeridos incluso si telÃ©fono/avatar estÃ¡n vacÃ­os. No requiere password; `newPassword` opcional impone un cambio obligatorio y revoca sesiones. `mustChangePassword:true` opcional permite imponer ese cambio; false se rechaza porque solo el usuario puede completarlo. Correo Ãºnico normalizado a minÃºsculas, conflicto 409 incluso si pertenece a una cuenta eliminada. Respuestas de usuario: `{id,name,email,phone,avatarUrl,role,status,lastAccess,mustChangePassword}`. No devuelven hashes, secretos MFA, tokens de sesiones ni cÃ³digos de recuperaciÃ³n. DesactivaciÃ³n, eliminaciÃ³n, cambio de rol/correo/contraseÃ±a o imposiciÃ³n de cambio obligatorio revocan sesiones y desafÃ­os y anulan enlaces de recuperaciÃ³n. La huella de permisos de cada sesiÃ³n detecta cambios de role_permissions al siguiente acceso y revoca todas las sesiones de esa cuenta. La migraciÃ³n 005 revoca sesiones antiguas sin huella.

Perfil propio PUT acepta exclusivamente name, email, phone y avatarUrl. No acepta estado, rol, contraseÃ±a ni permisos. Cambio de correo requiere reautenticaciÃ³n y revoca sesiones. La contraseÃ±a cambia mediante su ruta separada.

Ajustes permitidos (PUT body `{ "value": "USD" }`):

| key | ValidaciÃ³n | PÃºblico |
|---|---|---|
| site_url | HTTPS sin credenciales | sÃ­ |
| admin_url | HTTPS sin credenciales | no |
| api_url | HTTPS sin credenciales | no |
| timezone | identificador IANA reconocido por PHP | sÃ­ |
| default_currency | CRC o USD | sÃ­ |

GET settings devuelve una lista `{id,key,value,valueType:"string",public:boolean}`. La visibilidad se define en cÃ³digo, no por un flag enviado por cliente o por registros arbitrarios de settings. No admite nuevas claves, secretos, credenciales MySQL, SMTP, CORS o variables del servidor. Estos URLs informativos no reconfiguran el servidor. Contacto, horarios, logos, favicon y redes usan exclusivamente Contenido web, evitando duplicaciÃ³n.

### Login y cambio obligatorio

POST /auth/login `{email,password}` conserva, para cuentas sin requisitos pendientes, `{data:{token,expiresAt,user}}`. Si MFA estÃ¡ activo devuelve `{data:{challenge:"mfa_login",challengeToken,expiresAt}}`, sin token de sesiÃ³n ni datos privados. Si solo requiere cambio de contraseÃ±a devuelve `{data:{challenge:"password_change",challengeToken,expiresAt}}`. DesafÃ­os vencen a los 5 minutos, se almacenan como hash, se vinculan a contraseÃ±a y permisos vigentes y son de un solo uso. No conceden acceso general.

Para mfa_login: POST /auth/mfa/challenge `{challengeToken,code:"123456"}` o `{challengeToken,recoveryCode:"..."}`. Nunca enviar ambos. Respuesta de Ã©xito: sesiÃ³n completa, o desafÃ­o password_change si aÃºn debe cambiar contraseÃ±a. Un cÃ³digo incorrecto consume ese desafÃ­o: volver al login, sujeto a lÃ­mites. Para password_change: POST /auth/password/required `{challengeToken,newPassword}`. Requiere nueva contraseÃ±a de 16â€“72 bytes, diferente de la anterior. Completa must_change_password, revoca sesiones/enlaces previos y emite una sesiÃ³n completa; si MFA estÃ¡ activo, el desafÃ­o debe proceder del MFA ya validado. No hay modo de evitar MFA mediante cambio obligatorio.

POST /auth/password/change `{currentPassword,newPassword}` exige contraseÃ±a actual y X-Reauth-Token vÃ¡lido para password.change. Devuelve `{data:{changed:true,loginRequired:true}}`, revocando todas las sesiones. Volver al login. RecuperaciÃ³n por correo mantiene MFA habilitado y tambiÃ©n obliga a pasar MFA en el siguiente login.

### ReautenticaciÃ³n sensible

POST /auth/reauth `{password,action}` donde action es users.manage, settings.manage, profile.edit, password.change o mfa.manage. Si MFA estÃ¡ activo aÃ±adir `code` o `recoveryCode`. Devuelve `{data:{reauthToken,challenge:"reauth",expiresAt}}`. Enviar ese token en `X-Reauth-Token` junto al Bearer en una Ãºnica operaciÃ³n del propÃ³sito solicitado. CORS permite este encabezado. Vence a los 5 minutos y estÃ¡ vinculado al usuario y a la sesiÃ³n actual. No concede permisos por sÃ­ mismo; la operaciÃ³n comprueba su permiso en el servidor. No reutilizar despuÃ©s de un Ã©xito. Validaciones/transacciones fallidas no consumen un token confirmado Ãºnicamente dentro de la transacciÃ³n que fue revertida.

### RecuperaciÃ³n SMTP

POST /auth/password/forgot `{email}` devuelve siempre el mismo cuerpo para correos activos/inexistentes/inactivos: `{data:{message:"Si existe una cuenta activa, recibirÃ¡s un enlace de recuperaciÃ³n."}}`. No devuelve el token. Un correo activo recibe un enlace HTTPS con fragmento `#token=...`, vÃ¡lido 30 minutos, con token de 256 bits almacenado solo como SHA-256. Una solicitud nueva invalida enlaces anteriores. POST /auth/password/reset `{token,newPassword}` verifica vencimiento/un solo uso/estado activo, cambia contraseÃ±a y revoca sesiones/desafÃ­os/enlaces. Devuelve `{data:{changed:true,loginRequired:true}}`; un enlace invÃ¡lido, usado o vencido responde 401 INVALID_RESET. No crea sesiÃ³n ni elimina MFA.

Sin SMTP correctamente configurado todas las solicitudes reciben 503 SMTP_NOT_CONFIGURED. No se simula entrega. SMTP y URL de recuperaciÃ³n se configuran Ãºnicamente en config.local.php del servidor; instalaciÃ³n, TLS, remitente y pruebas reales pendientes se detallan en docs/accounts-install.md. El mensaje SMTP nunca se registra en logs. El frontend debe leer/eliminar el fragmento, conservar token en memoria y no enviarlo a analytics.

### MFA TOTP

1. Reautenticar con action mfa.manage y llamar POST /auth/mfa/enroll. Devuelve solamente `otpauthUri` para configurar el autenticador. Es la Ãºnica respuesta de aprovisionamiento que contiene el secreto, protegida por sesiÃ³n y contraseÃ±a reciente: mostrar QR local, no enviarlo a servicios externos, logs o almacenamiento persistente. Los GET de perfil/usuarios nunca exponen ese secreto.
2. Reautenticar de nuevo y POST /auth/mfa/confirm `{code}`. El secreto se almacena cifrado con Sodium secretbox y clave de 32 bytes externa a MySQL. TOTP RFC 6238 SHA-1, 6 dÃ­gitos/30 segundos, ventana Â±1 paso y contador antirrepeticiÃ³n. La confirmaciÃ³n activa MFA, revoca sesiones y entrega 10 recoveryCodes aleatorios una sola vez; mostrar para que el usuario los guarde de forma privada y volver al login.
3. Login entrega mfa_login; superarlo con un cÃ³digo o cÃ³digo de recuperaciÃ³n. Cada recoveryCode se almacena solo como hash y se consume una vez. Los cÃ³digos TOTP ya usados no pueden reutilizarse, incluso para reautenticar en el mismo intervalo: esperar al prÃ³ximo cÃ³digo o usar uno de recuperaciÃ³n.
4. POST /auth/mfa/recovery-codes, con reautenticaciÃ³n MFA, invalida cÃ³digos anteriores y entrega nuevos una vez. POST /auth/mfa/disable exige reautenticaciÃ³n de contraseÃ±a + MFA/recuperaciÃ³n, elimina secreto/cÃ³digos y revoca todas las sesiones. Devuelve enabled:false y loginRequired:true.

No se emite sesiÃ³n completa antes de MFA. Login de cuentas sin MFA permanece compatible. Secretos MFA anteriores que no estÃ©n cifrados en este formato deben recuperarse mediante mantenimiento privado autorizado; no hay fallback a texto plano ni bypass. Falta de Sodium/clave produce 503 MFA_NOT_CONFIGURED en las operaciones que requieren cifrado.

LÃ­mites por IP/15 minutos: login 10, MFA login/confirm 10, recuperaciÃ³n 5, reset/cambio obligatorio/cambio propio 10, reauth 10, alta/desactivaciÃ³n/rotaciÃ³n MFA 5, usuarios lectura 150/escritura 40, ajustes lectura 150/escritura 30. AdemÃ¡s buckets independientes de IP: login por correo 8, MFA login por cuenta 8, recuperaciÃ³n por correo 3 y reauth por cuenta 8. Bloqueo temporal del usuario despuÃ©s de fallos de contraseÃ±a. 429 incluye Retry-After:900 expuesto por CORS. AuditorÃ­a registra acciones y actor/request ID, nunca contraseÃ±as, tokens, secretos o cuerpos de correo. InstalaciÃ³n final y SMTP real en Hostinger necesitan validaciÃ³n posterior autorizada.
