from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
TOOL = ROOT / 'tools' / 'db_sb13.php'

checks = []
def check(cond, msg):
    checks.append(bool(cond))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

help_run = subprocess.run(['php', str(TOOL), '--help'], text=True, capture_output=True)
check(help_run.returncode == 0, 'SB-13 tool help exits successfully')
check('audit' in help_run.stdout and 'apply --backup-confirmed' in help_run.stdout and 'verify' in help_run.stdout, 'SB-13 tool documents all modes')

refused = subprocess.run(['php', str(TOOL), 'apply'], text=True, capture_output=True)
check(refused.returncode == 5, 'apply without backup confirmation is refused before migration')
check('REFUSED' in refused.stderr and '--backup-confirmed' in refused.stderr, 'backup refusal is explicit')
check('Database connection' not in refused.stderr and 'could not find driver' not in refused.stderr.lower(), 'backup refusal occurs before DB connection attempt')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} SB-13 CLI safety checks passed.')
