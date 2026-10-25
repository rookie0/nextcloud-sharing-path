#!/usr/bin/env python3
"""Build one reproducible sharingpath archive from tracked application files."""
import gzip
import io
from pathlib import Path
import subprocess
import tarfile
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parent.parent
RUNTIME = {'appinfo': {'.php', '.xml'}, 'lib': {'.php'}, 'js': {'.js'},
           'templates': {'.php'}, 'img': {'.svg', '.png', '.jpg'}}
DOCUMENTS = {'COPYING', 'README.md', 'CHANGELOG.md'}


def build():
    info = ET.parse(ROOT / 'appinfo/info.xml').getroot()
    app_id, version = info.findtext('id'), info.findtext('version')
    if app_id != 'sharingpath' or not version or not all(c.isdigit() or c == '.' for c in version):
        raise ValueError('Unexpected app id or version')
    tracked = subprocess.check_output(['git', 'ls-files', '-z'], cwd=ROOT).decode().split('\0')
    files = []
    for name in sorted(filter(None, tracked)):
        path = Path(name)
        allowed = name in DOCUMENTS or (path.parts[0] in RUNTIME and path.suffix in RUNTIME[path.parts[0]])
        if not allowed:
            continue
        source = ROOT / path
        if any(part.startswith('.') for part in path.parts) or source.is_symlink() or not source.is_file():
            raise ValueError('Unsafe application file: ' + name)
        if ROOT not in source.resolve().parents:
            raise ValueError('Application file leaves repository: ' + name)
        files.append((name, source))
    if 'appinfo/info.xml' not in [name for name, _ in files]:
        raise ValueError('Missing tracked app metadata')
    output = ROOT / 'build' / ('sharingpath-' + version + '.tar.gz')
    output.parent.mkdir(exist_ok=True)
    with output.open('wb') as raw, gzip.GzipFile(fileobj=raw, mode='wb', filename='', mtime=0) as zipped:
        with tarfile.open(fileobj=zipped, mode='w', format=tarfile.USTAR_FORMAT) as archive:
            for name, source in files:
                data = source.read_bytes()
                if b'PRIVATE KEY-----' in data:
                    raise ValueError('Private key material in application file: ' + name)
                entry = tarfile.TarInfo(app_id + '/' + name)
                entry.size, entry.mode, entry.mtime = len(data), 0o644, 0
                archive.addfile(entry, io.BytesIO(data))
    print(output)
    return output


if __name__ == '__main__':
    build()
