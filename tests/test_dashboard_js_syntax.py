from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
script = ROOT / 'public' / 'js' / 'dashboard.js'
proc = subprocess.run(['node', '--check', str(script)], capture_output=True, text=True)
print(('PASS' if proc.returncode == 0 else 'FAIL') + ': dashboard external JavaScript parses with Node.js')
if proc.returncode != 0:
    print(proc.stderr)
    raise SystemExit(proc.returncode)
