from pathlib import Path

root = Path(__file__).resolve().parents[1]
backend = (root / 'app/mail/mail_widget.php').read_text(encoding='utf-8')
js = (root / 'public/js/mail-widget.js').read_text(encoding='utf-8')
api = (root / 'public/api_v1.php').read_text(encoding='utf-8')
css = (root / 'public/css/mail-widget.css').read_text(encoding='utf-8')

checks = {
    'read-only INBOX uses EXAMINE': "->examine('INBOX')" in backend,
    'headers fetched with BODY.PEEK path': '->withHeaders()' in backend and '->leaveUnread()' in backend,
    'flags fetched for unread state': '->withFlags()' in backend and 'isSeen()' in backend,
    'V1.9-C does not request message body': '->withBody()' not in backend and '->text(' not in backend and '->html(' not in backend,
    'V1.9-C does not request attachments': '->attachments(' not in backend,
    'Mail Widget DB operations owner scoped': 'widget_owner = :owner' in backend,
    'Mail Account DB operations owner scoped': 'mail_account_find_owned($ownerId' in backend,
    'Mail Widget API uses explicit prefix dispatch': "str_starts_with($action, 'mail.widget.')" in api,
    'Mail remote values rendered as text': '.text(String(message.from' in js and '.text(String(message.subject' in js,
    'Mail JS does not use html() for remote content': '.html(' not in js,
    'Mail list is asynchronous': "apiRequest('mail.widget.list'" in js and "apiRequest('mail.widget.fetch'" in js,
    'Mail manual refresh exists': 'mail-widget-refresh' in js,
    'Mail CSS exists': '.mail-card' in css and '.mail-unread' in css,
}

failed = [label for label, ok in checks.items() if not ok]
for label, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + label)
if failed:
    raise SystemExit('V1.9-C static failures: ' + ', '.join(failed))
print('PASS: V1.9-C static checks')
