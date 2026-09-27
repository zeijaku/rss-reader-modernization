from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
scripts = [
    ROOT / 'public' / 'js' / 'dashboard-core.js',
    ROOT / 'public' / 'js' / 'dashboard.js',
]

failed = False
for script in scripts:
    proc = subprocess.run(['node', '--check', str(script)], capture_output=True, text=True)
    print(('PASS' if proc.returncode == 0 else 'FAIL') + f': {script.name} parses with Node.js')
    if proc.returncode != 0:
        failed = True
        print(proc.stderr)

raise SystemExit(1 if failed else 0)
