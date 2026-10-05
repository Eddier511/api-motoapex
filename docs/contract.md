# Contrato API v1

Base: `https://TU_API/v1`. Solicitudes con cuerpo: `Content-Type: application/json`. Respuestas: `{ "data": ... }` o `{ "error": { "code": "...", "message": "..." } }`. Los errores son 400/401/403/404/405/409/413/415/422/429/500. El encabezado `X-Request-ID` permite localizar errores sin publicar detalles internos.

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
