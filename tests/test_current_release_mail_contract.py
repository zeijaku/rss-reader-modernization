#!/usr/bin/env python3
"""Durable contract: formal Runtime ZIP includes Mail dependencies, not generated Source data."""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)


ci = text('.github/workflows/ci.yml')
release = text('.github/workflows/release.yml')
builder = text('tools/build_release_package.py')
verifier = text('tools/verify_release_package.py')
complete = text('tools/build_complete_package.py')
complete_verify = text('tools/verify_complete_package.py')
mailer = text('tools/build_mail_dependencies.sh')
docs = text('docs/release-package.md')
runner = text('tests/run-current-features.sh')

check('directorytree/imapengine": "1.25.6"' in mailer,
      'Mail provisioning retains reviewed exact ImapEngine version')
check('--no-scripts --no-plugins' in mailer and 'composer audit' in mailer,
      'Mail dependencies run no unreviewed Composer hooks and receive audit')
check('mail_client_available()' in mailer and 'AppMailPinnedImapStream' in mailer,
      'Mail dependency builder verifies actual PHP class compatibility')
for workflow, name in [(ci, 'release-readiness dry run'), (release, 'official Release')]:
    check('bash tools/build_mail_dependencies.sh build/mail' in workflow,
          f'{name} installs audited Mail dependencies')
    check('--mail-dependencies-dir build/mail' in workflow,
          f'{name} passes Mail vendor to Runtime packager')
    check('--require-mail-dependencies' in workflow,
          f'{name} rejects a vendor-free Runtime ZIP')
    check('mail_client_available()' in workflow and '/vendor/autoload.php' in workflow,
          f'{name} verifies Mail autoload after building Runtime ZIP')
check('vendor/autoload.php' in builder and 'composer.lock' in builder
      and 'directorytree/imapengine/composer.json' in builder,
      'release builder collects Composer vendor, manifest and lock')
check('mail_dependencies_dir' in builder and 'mail_dependencies=\' + (' in builder,
      'Runtime manifest explicitly describes Composer Mail inclusion')
check('vendor/autoload.php' in verifier and 'composer.lock' in verifier
      and 'composer-pinned' in verifier,
      'Runtime verifier requires and checks Mail vendor provenance')
check("'build'" in complete and 'EXCLUDED_TOP' in complete
      and "rel.startswith('build/')" in complete_verify,
      'Complete Source excludes generated Composer and dry-run workspace')
check('build_mail_dependencies.sh build/mail' in docs
      and '--require-mail-dependencies' in docs,
      'package documentation mirrors mandatory Mail verification workflow')
check('test_current_release_mail_contract.py' in runner,
      'Current feature runner preserves Mail package contract')
check('reader_fix_package:' not in ci,
      'obsolete branch-specific Dev10 package job is removed from current CI')

fail = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {fail}')
raise SystemExit(1 if fail else 0)
