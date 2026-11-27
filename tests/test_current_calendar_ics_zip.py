#!/usr/bin/env python3
from pathlib import Path
import io
import subprocess
import zipfile

root = Path(__file__).resolve().parents[1]
code = '''require 'app/calendar_ics_zip.php';
echo calendar_transfer_zip(['calendar-1.ics'=>"BEGIN:VCALENDAR\\r\\nEND:VCALENDAR\\r\\n",'calendar-2.ics'=>"日本語\\n"],8192);'''
raw = subprocess.check_output(['php', '-r', code], cwd=root)
with zipfile.ZipFile(io.BytesIO(raw)) as archive:
    assert archive.namelist() == ['calendar-1.ics', 'calendar-2.ics']
    assert archive.testzip() is None
    assert archive.read('calendar-1.ics') == b'BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n'
    assert archive.read('calendar-2.ics') == '日本語\n'.encode()
    assert all(info.compress_type == zipfile.ZIP_STORED for info in archive.infolist())
for expression in [
    "calendar_transfer_zip(['../calendar-1.ics'=>'x'],8192)",
    "calendar_transfer_zip(['calendar-1.ics'=>str_repeat('x',9000)],8192)",
]:
    php = "require 'app/calendar_ics_zip.php'; try {" + expression + "; exit(1);} catch (InvalidArgumentException|LengthException $e) {exit(0);}"
    subprocess.run(['php','-r',php], cwd=root, check=True)
print('PASS: independent ZIP reader, CRC, UTF-8, safe paths and byte bounds')
