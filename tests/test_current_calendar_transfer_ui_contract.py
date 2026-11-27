from pathlib import Path
root=Path(__file__).resolve().parents[1]
read=lambda p:(root/p).read_text(encoding='utf8')
modal=read('app/view/dashboard_modals.php')
pane=read('app/view/calendar_transfer_pane.php')
js=read('public/js/calendar-transfer.js')
loader=read('public/js/calendar.js')
css=read('public/css/calendar-transfer.css')
assert modal.count('id="calendarSourceManager"')==1
assert all(x in modal for x in ['calendarManagerLocalTab','calendarManagerIcsTab','calendarManagerTransferTab','calendar_transfer_pane.php'])
assert all(x in pane for x in ['calendarTransferFile','calendarTransferConfirm','calendarTransferApply','calendarTransferPreviewPanel','calendarTransferDownload','calendarTransferPeriodWarning'])
assert 'calendar-transfer.js' in loader and 'calendar-transfer.css' in loader
assert all(x in js for x in ['calendar.transfer.preview','calendar.transfer.import','calendar.transfer.export','preview_token','new FormData','processData: false','contentType: false','textContent','URL.revokeObjectURL','calendar:occurrenceChanged','calendar:transferImported','!ids(\'Confirm\').checked'])
assert '.innerHTML' not in js and '.html(' not in js
assert '@media (max-width: 575.98px)' in css and '44px' in css
print('PASS: Calendar transfer tab, controls, safe text, explicit preview confirmation, multipart, download and mobile contract')
