from pathlib import Path
import re
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
source = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
marker = '<script>\n\n/* Secure Baseline API helper */'
pos = source.find(marker)
if pos < 0:
    raise SystemExit('dashboard inline script marker not found')
script = source[pos + len('<script>'):]
end = script.find('</script>')
if end < 0:
    raise SystemExit('dashboard inline script close tag not found')
script = script[:end]
# PHP emits only fetch_content(<int>) calls in this block. Remove server-side blocks for JS parser validation.
script = re.sub(r'<\?php.*?\?>', '', script, flags=re.S)
with tempfile.NamedTemporaryFile('w', suffix='.js', delete=False, encoding='utf-8') as fp:
    fp.write(script)
    name = fp.name
proc = subprocess.run(['node', '--check', name], capture_output=True, text=True)
print(('PASS' if proc.returncode == 0 else 'FAIL') + ': dashboard inline JavaScript parses with Node.js')
if proc.returncode != 0:
    print(proc.stderr)
    raise SystemExit(proc.returncode)
