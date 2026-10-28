"""Verify deploy layout and private-file exclusions, including ZIP permissions."""
from pathlib import Path
import stat
import hashlib
import json
import sys
import zipfile

archive_path = Path(sys.argv[1])
with zipfile.ZipFile(archive_path) as archive:
    assert archive.testzip() is None
    names = set(archive.namelist())
    assert {'database/007_optional_promotion_dates.sql','docs/promotions-optional-dates.md'}<=names
    manifest=json.loads(archive.read('deployment-manifest.json'))
    assert '007_optional_promotion_dates' in manifest['requiredMigrations']
    assert set(manifest['fileSha256'])==names-{'deployment-manifest.json'}
    for name,digest in manifest['fileSha256'].items():
        assert hashlib.sha256(archive.read(name)).hexdigest()==digest
    assert {n for n in names if n.startswith('public_html/')} == {'public_html/index.php','public_html/.htaccess'}
    assert {'database/006_security_hardening.sql','bin/process-mail-queue.php','config/php-security.ini','dependencies.json','docs/security-hardening.md'} <= names
    assert 'src/bootstrap.php' in names and 'src/repository.php' in names
    assert 'public/index.php' not in names and '.htaccess' not in names
    assert 'config.local.php' not in names and 'tests/setup.php' not in names
    assert not any(n.startswith(('.git/', 'tests/')) for n in names)
    assert not any(n.startswith('/') or '..' in Path(n).parts for n in names)
    assert 'Require all granted' in archive.read('public_html/.htaccess').decode()
    for info in archive.infolist():
        assert stat.S_IMODE(info.external_attr >> 16) == 0o644
print('Hostinger package layout, exclusions, integrity and permissions passed')
