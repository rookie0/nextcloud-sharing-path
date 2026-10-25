#!/usr/bin/env python3
import hashlib
from pathlib import Path
import shutil
import subprocess
import tarfile
import tempfile

root = Path(__file__).resolve().parent.parent
with tempfile.TemporaryDirectory(prefix='sharingpath-package-test-') as temp:
    work = Path(temp)
    for name in ['appinfo', 'lib', 'js', 'templates', 'img', 'tools']:
        shutil.copytree(root / name, work / name)
    for name in ['COPYING', 'README.md', 'CHANGELOG.md', 'build.sh']:
        shutil.copy(root / name, work / name)
    subprocess.run(['git', 'init', '-q', str(work)], check=True)
    subprocess.run(['git', 'add', '.'], cwd=work, check=True)
    (work / 'certificate').mkdir()
    (work / 'certificate' / 'dummy.key').write_text('DUMMY-KEY')
    (work / '.env').write_text('DUMMY-TOKEN')
    (work / 'lib' / 'untracked.php').write_text('DUMMY-UNTRACKED')
    (work / 'lib' / 'accidental.key').write_text('DUMMY-KEY')
    subprocess.run(['git', 'add', 'lib/accidental.key', 'certificate/dummy.key', '.env'], cwd=work, check=True)
    subprocess.run(['bash', 'build.sh'], cwd=work, check=True, stdout=subprocess.DEVNULL)
    archive = next((work / 'build').glob('*.tar.gz'))
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    with tarfile.open(archive) as tf:
        members = tf.getmembers()
        assert all(m.isfile() and m.name.startswith('sharingpath/') for m in members)
        assert not any('certificate' in m.name or m.name.endswith(('.key', '.env', 'untracked.php')) for m in members)
        assert all(not tf.extractfile(m).read().startswith(b'DUMMY-') for m in members)
        assert any(m.name == 'sharingpath/lib/Controller/PathController.php' for m in members)
    subprocess.run(['bash', 'build.sh'], cwd=work, check=True, stdout=subprocess.DEVNULL)
    assert hashlib.sha256(archive.read_bytes()).hexdigest() == digest
    target = work / 'lib/Controller/PathController.php'
    target.unlink()
    target.symlink_to(work / '.env')
    rejected = subprocess.run(['bash','build.sh'],cwd=work,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    assert rejected.returncode != 0
print('Packaging: tracked allowlist, secret exclusions, reproducibility and symlink rejection passed')
