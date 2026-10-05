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
