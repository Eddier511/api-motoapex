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
