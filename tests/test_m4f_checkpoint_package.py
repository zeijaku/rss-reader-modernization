#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path, PurePosixPath
import hashlib
import re
import sys
import tempfile
import zipfile

checks = 0


def check(condition: bool, message: str) -> None:
    global checks
    checks += 1
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


if len(sys.argv) != 2:
    raise SystemExit('Usage: python tests/test_m4f_checkpoint_package.py <checkpoint.zip>')
zip_path = Path(sys.argv[1]).resolve()
check(zip_path.is_file(), 'M4-F checkpoint ZIP exists')

with zipfile.ZipFile(zip_path) as archive:
    check(archive.testzip() is None, 'checkpoint ZIP compressed data passes CRC verification')
    infos = archive.infolist()
    names = [info.filename for info in infos if not info.is_dir()]
    check(len(names) == len(set(names)), 'checkpoint ZIP contains no duplicate file entries')
    check(all('\\' not in name for name in names), 'checkpoint ZIP paths use forward slashes')
    check(all(not PurePosixPath(name).is_absolute() for name in names), 'checkpoint ZIP contains no absolute path')
    check(all('..' not in PurePosixPath(name).parts for name in names), 'checkpoint ZIP contains no parent traversal path')
    top = {PurePosixPath(name).parts[0] for name in names}
    check(len(top) == 1, 'checkpoint ZIP contains one project top-level directory')
    check(top == {'rss-reader-modernization-m4-f-r1'}, 'checkpoint ZIP top-level directory is exact')
    lower = [name.lower() for name in names]
    check(not any(name.endswith('.zip') for name in lower), 'checkpoint ZIP contains no nested ZIP')
    forbidden_names = {'config/local.php', '.env', 'rss.sql', 'rss.zip'}
    check(not any('/'.join(PurePosixPath(name).parts[1:]) in forbidden_names for name in names), 'checkpoint ZIP excludes forbidden named files')
    forbidden_ext = ('.sqlite', '.sqlite3', '.db', '.dump', '.bak', '.backup', '.log', '.pid')
    check(not any(name.endswith(forbidden_ext) for name in lower), 'checkpoint ZIP excludes runtime/database file extensions')

    with tempfile.TemporaryDirectory(prefix='rss-m4f-checkpoint-') as tmp:
        archive.extractall(tmp)
        project = Path(tmp) / 'rss-reader-modernization-m4-f-r1'
        manifest = project / 'docs/package-manifest-m4-f-r1.txt'
        check(manifest.is_file(), 'M4-F checkpoint manifest exists after extraction')
        expected: dict[str, str] = {}
        for line in manifest.read_text(encoding='utf-8').splitlines():
            if not line.strip():
                continue
            digest, rel = line.split('  ', 1)
            check(bool(re.fullmatch(r'[0-9a-f]{64}', digest)), f'manifest digest format is valid: {rel}')
            expected[rel] = digest
        actual_files = {p.relative_to(project).as_posix(): p for p in project.rglob('*') if p.is_file()}
        check(set(actual_files) == set(expected) | {'docs/package-manifest-m4-f-r1.txt'}, 'checkpoint manifest file set matches extracted ZIP')
        for rel, digest in expected.items():
            actual = hashlib.sha256(actual_files[rel].read_bytes()).hexdigest()
            check(actual == digest, f'checkpoint manifest SHA-256 matches: {rel}')

        for rel in [
            'RELEASE_NOTES.md', 'tools/build_release_package.py', 'tools/verify_release_package.py',
            'tools/m4f_environment_probe.php', 'tools/m4f_evidence_gate.py',
            'docs/release-package.md', 'docs/tag-and-github-release.md',
            'docs/m4-f-implementation.md', 'docs/m4-f-validation.md',
            'docs/m4-f-validation-template.json', 'docs/test-report-m4-f.md',
            'tests/test_m4f_release_candidate.py', 'tests/test_m4f_environment_probe.py',
            'tests/test_m4f_evidence_gate.py', 'tests/test_m4f_documentation.py',
            'tests/test_m4f_checkpoint_package.py', 'LICENSE', 'THIRD_PARTY_NOTICES.md',
            'var/m4f-evidence/.gitkeep',
        ]:
            check((project / rel).is_file(), f'M4-F checkpoint file is packaged: {rel}')

        version = (project / 'app/version.php').read_text(encoding='utf-8')
        check("APP_VERSION = '1.0.0-rc1'" in version, 'extracted checkpoint has RC1 version')
        check("APP_VERSION_LABEL = 'RSS Reader Modernization 1.0.0-RC1'" in version, 'extracted checkpoint has RC1 label')
        check(not any(project.glob('rss-reader-modernization-1.0.0-rc1*.zip')), 'checkpoint does not embed RC ZIP')

        evidence_files = [p for p in (project / 'var/m4f-evidence').iterdir() if p.is_file() and p.name != '.gitkeep']
        check(not evidence_files, 'checkpoint excludes private M4-F evidence files')

        secret_patterns = [
            re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----'),
            re.compile(r'\bAKIA[0-9A-Z]{16}\b'),
            re.compile(r'\bsk-[A-Za-z0-9_-]{20,}\b'),
        ]
        secret_hits = []
        for dirname in ['.github', 'app', 'public', 'config', 'database', 'tools', 'docs']:
            for path in (project / dirname).rglob('*'):
                if not path.is_file():
                    continue
                try:
                    text = path.read_text(encoding='utf-8')
                except (UnicodeDecodeError, OSError):
                    continue
                if any(pattern.search(text) for pattern in secret_patterns):
                    secret_hits.append(path.relative_to(project).as_posix())
        check(not secret_hits, 'extracted checkpoint contains no high-signal secret pattern')

        for runtime in ['var/session', 'var/log', 'var/cache/feed', 'var/db-migration', 'var/security/login-throttle']:
            directory = project / runtime
            generated = [p for p in directory.iterdir() if p.is_file() and p.name != '.gitkeep'] if directory.exists() else []
            check(not generated, f'extracted checkpoint runtime directory is clean: {runtime}')

print(f'All {checks} M4-F checkpoint package checks passed.')
