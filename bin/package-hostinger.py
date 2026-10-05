"""Build the same credential-free Hostinger layout locally and in CI."""
from pathlib import Path
import argparse
import json
import stat
import zipfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, default=root.parent / 'api-motoapex-hostinger.zip')
args = parser.parse_args()
destination = args.output.resolve()
instructions = '''INSTALACION API MOTOAPEX

1. Extrae este ZIP en la carpeta del sitio de la API que CONTIENE public_html.
   No extraigas todo dentro de public_html.
   Debe quedar:
     public_html/index.php
     public_html/.htaccess
     src/bootstrap.php
     src/repository.php
     src/validation.php
     bin/create-admin.php
     database/002_api_support.sql
     config.example.php
   Si el administrador de archivos solo muestra public_html, abre la carpeta
   superior o usa SFTP para colocar src y la configuracion fuera de ella.

2. Copia config.example.php como config.local.php, junto a src y public_html.
   Escribe la contrasena real de MySQL SOLO en config.local.php del servidor.
   La contrasena no viene incluida en el ZIP.

3. Como el esquema inicial ya esta importado, importa UNICAMENTE
   database/002_api_support.sql, una sola vez, despues de un respaldo.
   No vuelvas a importar schema.sql. No borres tablas para instalar la API.

4. El document root debe ser public_html. Reemplaza su .htaccess por el
   incluido en public_html/.htaccess, NO por el de la raiz del repositorio.
   El de la raiz tiene Require all denied para proteger archivos privados.
   Permisos normales: carpetas 755 y archivos publicos 644; no uses 777.
   config.local.php: 600 si PHP corre como tu usuario; no hacerlo publico.
   No borres ni sobrescribas tu configuracion privada al actualizar.
   Si persiste 403 HTML, revisa los logs de acceso/errores, propietario,
   document root y reglas de hPanel/CDN. No desactives la seguridad global.

5. Selecciona PHP 8.3 o superior con PDO MySQL y habilita HTTPS.
   Desactiva mostrar errores PHP al visitante.

6. Crea el usuario administrador mediante bin/create-admin.php, desde terminal:
   define temporalmente la variable MOTOAPEX_ADMIN_PASSWORD (16-72 bytes),
   ejecuta php bin/create-admin.php TU_CORREO 'TU_NOMBRE' y elimina la variable.
   Las credenciales de MySQL NO sirven para iniciar sesion en el admin.
   Consulta README.md si no tienes terminal; no hay instalador web.

7. Verifica:
   https://darksalmon-quetzal-730302.hostingersite.com/v1/health
   La respuesta debe ser {"data":{"status":"ok"}}.

8. Configura web/admin con:
   VITE_API_BASE_URL=https://darksalmon-quetzal-730302.hostingersite.com/v1
   Recompila ambos frontends. Al activar el dominio definitivo, usa:
   VITE_API_BASE_URL=https://api.motoapexcr.com/v1

Consulta docs/contract.md para rutas y campos. El paquete es la primera version:
MFA, recuperacion de contrasena, promociones, contenido y gestion de usuarios
aun no tienen flujos completos. Las imagenes se guardan como enlaces HTTPS.
'''

files = {}
for directory in ('public', 'src'):
    for file in (root / directory).rglob('*'):
        if file.is_file():
            path = file.relative_to(root).as_posix()
            if directory == 'public':
                path = 'public_html/' + file.relative_to(root / 'public').as_posix()
            files[path] = file.read_bytes()
for path in ('bin/create-admin.php', 'database/002_api_support.sql',
             'config.example.php', 'README.md', 'docs/contract.md',
             'docs/integration-prompts.md', 'docs/hosting-403.md'):
    files[path] = (root / path).read_bytes()
files['LEEME-INSTALACION.txt'] = instructions.encode('utf-8')
files['deployment-manifest.json'] = json.dumps({
    'documentRoot': 'public_html',
    'entrypoint': 'public_html/index.php',
    'privateConfig': 'config.local.php (create on server, not included)',
    'publicFiles': ['public_html/index.php', 'public_html/.htaccess'],
    'temporaryBase': 'https://darksalmon-quetzal-730302.hostingersite.com/v1',
    'productionBase': 'https://api.motoapexcr.com/v1',
}, indent=2).encode()
assert not any('config.local.php' == path for path in files)
assert set(path for path in files if path.startswith('public_html/')) == {'public_html/index.php', 'public_html/.htaccess'}
with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path, data in files.items():
        info = zipfile.ZipInfo(path)
        info.create_system = 3
        info.external_attr = (stat.S_IFREG | 0o644) << 16
        info.compress_type = zipfile.ZIP_DEFLATED
        archive.writestr(info, data)
with zipfile.ZipFile(destination) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist()) == len(files)
print(f'Created and verified: {destination} ({len(files)} files)')
