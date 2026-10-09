#!/usr/bin/env python3
"""Current, fail-closed contract for the deployed public PHP allowlist."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
POLICY = (PUBLIC / '.htaccess').read_text(encoding='utf-8')
ROOT_POLICY = (ROOT / '.htaccess').read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


# Only direct entry points are allowed to execute; nested PHP files are never public.
actual = {p.name for p in PUBLIC.glob('*.php') if p.is_file()}
nested = sorted(str(p.relative_to(PUBLIC)) for p in PUBLIC.rglob('*.php') if p.parent != PUBLIC)
check(not nested, f'no nested PHP endpoints: {nested}')

# Parse the exact allowlist gate, not comments/redirects or historical endpoint docs.
allow_rules = [line.strip() for line in POLICY.splitlines()
               if line.strip().startswith('RewriteRule ^(?!') and 'php' in line]
check(len(allow_rules) == 1, 'exactly one default-deny PHP allowlist rule')
rule = allow_rules[0]
check(rule.endswith(' - [F,L,NC]'), 'unknown PHP paths are forbidden and case-insensitive')
match = re.fullmatch(r'RewriteRule \^\(\?!([^\s]+)\)\.\*\\\.php\$ - \[F,L,NC\]', rule)
check(match is not None, 'allowlist keeps explicit anchored negative-lookahead grammar')
expressions = match.group(1).split('|')
allowed = set()
for expression in expressions:
    m = re.fullmatch(r'([a-zA-Z][a-zA-Z0-9_-]*)\\\.php\$', expression)
    check(m is not None, f'anchored PHP allowlist entry: {expression}')
    allowed.add(m.group(1) + '.php')
check(len(allowed) == len(expressions), 'no duplicate PHP allowlist entries')
check(allowed == actual, f'PHP allowlist and disk endpoints match (allowlist={sorted(allowed-actual)}, unlisted={sorted(actual-allowed)})')
check('Options -Indexes' in ROOT_POLICY and 'Options -Indexes' in POLICY, 'directory listings disabled at root and public')
check('RewriteRule ^(?:app|config|tools|var)(?:/|$) - [F,L,NC]' in ROOT_POLICY,
      'root policy forbids private directories including the runtime storage')
check('<FilesMatch "(^\\.|' in POLICY and 'Require all denied' in POLICY,
      'public policy forbids dotfiles and sensitive extensions')
check('RewriteRule ^public(?:/|$) - [L]' in ROOT_POLICY,
      'root rewrite preserves public internal routing without recursion')
print(f'All current public endpoint security checks passed ({len(actual)} endpoints).')
