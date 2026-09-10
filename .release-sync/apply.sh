#!/usr/bin/env bash
set -euo pipefail

cat .release-sync/part-0[0-5] | tr -d '\r\n' | base64 --decode > /tmp/v134-runtime-src.tar.gz
echo '44de5d991657fabd52e8132afda7faea621a2dd05123a433e27cd0cd034ee20e  /tmp/v134-runtime-src.tar.gz' | sha256sum -c -
gzip -t /tmp/v134-runtime-src.tar.gz
tar -tzf /tmp/v134-runtime-src.tar.gz > /tmp/v134-files.txt
if grep -E '(^/|(^|/)\.\.(/|$))' /tmp/v134-files.txt; then echo 'Unsafe archive path found.' >&2; exit 1; fi
if grep -Ev '^(app(/|$)|public(/|$)|database/migrations(/|$))' /tmp/v134-files.txt; then echo 'Unexpected archive path found.' >&2; exit 1; fi
tar -xzf /tmp/v134-runtime-src.tar.gz -C .

mkdir -p app/mail/vendor/phpmailer/phpmailer/src
base='https://raw.githubusercontent.com/PHPMailer/PHPMailer/v7.1.1'
for file in LICENSE VERSION src/Exception.php src/OAuthTokenProvider.php src/PHPMailer.php src/SMTP.php; do
  curl --fail --location --silent --show-error "$base/$file" -o "app/mail/vendor/phpmailer/phpmailer/$file"
done
cat > /tmp/phpmailer.sha256 <<'EOF'
a1a33180d02960ab1c5de36cf20b1a2f0fe9888d83826ad263da5db52f1b183b  app/mail/vendor/phpmailer/phpmailer/LICENSE
511a8bbd0ef0e332c8286ff60c232bfc1c804c0ba11eee76606cdfdc1722de92  app/mail/vendor/phpmailer/phpmailer/VERSION
22ab858ae438d98f58f41f38ad2191d1b0d59570aebea0463a7948cfae1021b7  app/mail/vendor/phpmailer/phpmailer/src/Exception.php
f2318ea1b2841b2481b636e205c281a733f9de944a71ae403e0b59fa71219572  app/mail/vendor/phpmailer/phpmailer/src/OAuthTokenProvider.php
45599a196ae7944ee2dcd4f3d3da0ac4243513d346b2d77bbd15dbd0c37f7064  app/mail/vendor/phpmailer/phpmailer/src/PHPMailer.php
522bcf0d07be7e7e00114711db5c9ce2b4d59ac041c5ec36eaadd979d5fa7046  app/mail/vendor/phpmailer/phpmailer/src/SMTP.php
EOF
sha256sum -c /tmp/phpmailer.sha256

python3 <<'PY'
from pathlib import Path
readme=Path('README.md'); text=readme.read_text(encoding='utf-8')
text=text.replace('**Stable release:** `RSS Reader Modernization 1.33.1`','**Stable release:** `RSS Reader Modernization 1.34.0`',1)
text=text.replace('Release tag: `v1.33.1`','Release tag: `v1.34.0`',1)
marker='Version 1.33.1はRemote Filesの複数Upload対応を追加したPatch Releaseです。'
intro=('Version 1.34.0はMail WidgetへSMTP送信、Plain Text Compose／Reply、Sent保存、送信中表示、'
       '送信添付と受信／Sent添付の表示・ダウンロードを追加したReleaseです。SMTPは465 SSL/TLSまたは587 STARTTLSに限定し、'
       'IMAP Credential再利用または暗号化した個別SMTP Credentialに対応します。送信添付は最大5件・1件10 MiB・合計20 MiB、'
       '受信添付はメタ情報だけを一覧取得し、選択時に最大25 MiBまでオンデマンドで取得します。既存環境ではMigration 026、027を番号順に一度だけ適用します。\n\n')
if intro.strip() not in text:
    if marker not in text: raise SystemExit('README version marker not found')
    text=text.replace(marker,intro+marker,1)
current='## 現在できること'; bullet='- Mail WidgetによるIMAP受信／検索／Folder切替、SMTP Plain Text送信／Reply、Sent保存、送信／受信添付\n'
if current in text and bullet.strip() not in text:
    pos=text.index(current)+len(current); nl=text.find('\n',pos); text=text[:nl+1]+'\n'+bullet+text[nl+1:]
readme.write_text(text,encoding='utf-8')

changelog=Path('CHANGELOG.md'); old=changelog.read_text(encoding='utf-8')
entry='''## 1.34.0 - 2026-09-11

### Mail Send / Reply / Sent
- Added bounded SMTP settings to each Mail Account with 465 SSL/TLS or 587 STARTTLS, optional encrypted separate SMTP credentials, From Address/Name, and a connection/authentication test that sends no message.
- Added Plain Text Compose and Reply, including Reply-To preference, validated threading headers, and a visible send-progress state.
- Added Sent save modes Auto / Server / RSS Reader. Auto checks immediately, after 1 second, and after a further 2 seconds before bounded IMAP APPEND fallback; SMTP is never resent because Sent storage failed.

### Attachments
- Added outbound attachments for Compose and Reply: up to 5 files, 10 MiB per file and 20 MiB total, with malformed upload, dangerous executable/script extension, and clearly dangerous MIME rejection.
- Added received/Sent attachment metadata display and on-demand download. Binary content is not included in the normal message JSON response; download rechecks ownership, folder, part identifier and a 25 MiB receive limit.

### Security / compatibility
- Preserved Authentication, Session, CSRF, owner scope, IMAP/SMTP SSRF validation and validated-IP pinning, TLS verification, input validation and existing read-only IMAP list/search/body behavior.
- Bundled the required PHPMailer 7.1.1 subset with its upstream LICENSE. OAuthTokenProvider preparation is present, but OAuth2 authentication itself is not implemented.

### Database / finalization
- Added additive/idempotent migrations `026_v1_34_mail_smtp.sql` and `027_v1_34_mail_sent_save_mode.sql`. Existing Mail Accounts remain SMTP-disabled until configured; Sent mode defaults to `auto`.
- Production checkpoint verification completed for SMTP send, Reply, Sent handling, outbound attachments and received/Sent attachment download before formal release.
- Promoted the V1.34 Mail contract into the current feature suite and finalized application/asset revision at `1.34.0`.

'''
if not old.startswith('## 1.34.0 - '): changelog.write_text(entry+old,encoding='utf-8')

env=Path('config/.env.example'); text=env.read_text(encoding='utf-8')
mail_env='''# V1.34 Mail send/reply. Keep the real key only in private server configuration.
APP_MAIL_CREDENTIAL_KEY_ID=primary
APP_MAIL_CREDENTIAL_KEY_B64=replace-with-base64-encoded-32-byte-key
APP_MAIL_IMAP_TIMEOUT_SECONDS=5
APP_MAIL_SMTP_TIMEOUT_SECONDS=5

'''
anchor='# V1.29+ Remote File Manager.'
if 'APP_MAIL_CREDENTIAL_KEY_B64=' not in text:
    if anchor not in text: raise SystemExit('config env insertion anchor not found')
    text=text.replace(anchor,mail_env+anchor,1)
env.write_text(text,encoding='utf-8')

config=Path('docs/configuration.md'); text=config.read_text(encoding='utf-8')
mail_docs='''## Mail Widget（V1.34）

Mail WidgetのIMAP受信とSMTP送信は、保存CredentialをServer側の専用鍵で暗号化して扱います。SMTPは465 SSL/TLSまたは587 STARTTLSだけを許可し、TLS peer／hostname検証と既存のpublic-address-only target validationを維持します。

| Key | Default | Runtime制約 / 補足 |
|---|---:|---|
| `APP_MAIL_CREDENTIAL_KEY_ID` | `primary` | Mail Credential envelopeのKey ID |
| `APP_MAIL_CREDENTIAL_KEY_B64` | 空 | 必須。32-byte乱数をBase64化した値。DB／Git／Browserへ出さない |
| `APP_MAIL_IMAP_TIMEOUT_SECONDS` | `5` | IMAP接続／Commandのbounded Timeout |
| `APP_MAIL_SMTP_TIMEOUT_SECONDS` | `5` | SMTP接続／Commandのbounded Timeout |

Mail Account保存後にCredential keyを変更・紛失すると、既存IMAP Credentialと個別SMTP Credentialを復号できなくなります。Keyを変更した場合は対象AccountのCredential再入力が必要です。

送信添付のApplication上限は最大5件、1件10 MiB、合計20 MiBです。ただしHosting側の`upload_max_filesize`、`post_max_size`、Web Server request-body limit、Mail provider側のmessage-size policyの方が小さい場合は、そちらが実質上限になります。受信添付は本文JSONへ含めず、選択されたpartだけを最大25 MiBまでオンデマンド取得します。

'''
anchor='## X API Widget（上級者向け / Optional）'
if '## Mail Widget（V1.34）' not in text:
    if anchor not in text: raise SystemExit('configuration insertion anchor not found')
    text=text.replace(anchor,mail_docs+anchor,1)
backup='- `APP_REMOTE_CREDENTIAL_KEY_B64`を保管するSecret store（Remote Files利用時）'
if '- `APP_MAIL_CREDENTIAL_KEY_B64`を保管するSecret store（Mail利用時）' not in text: text=text.replace(backup,'- `APP_MAIL_CREDENTIAL_KEY_B64`を保管するSecret store（Mail利用時）\n'+backup,1)
config.write_text(text,encoding='utf-8')

deps=Path('docs/dependencies.md'); text=deps.read_text(encoding='utf-8')
backend='''## Backend dependency（V1.34）

| Component | Version | Runtime path | License |
|---|---:|---|---|
| PHPMailer | 7.1.1 | `app/mail/vendor/phpmailer/phpmailer/` | LGPL-2.1-only (`LICENSE`を同梱) |

V1.34ではComposer実行を本番要件にせず、SMTP送信に必要なPHPMailerの限定SourceをRepositoryへ同梱します。OAuthTokenProvider interfaceは含みますが、OAuth2認証実装はV1.34の対象外です。

'''
anchor='## Theme inventory'
if '## Backend dependency（V1.34）' not in text:
    if anchor not in text: raise SystemExit('dependencies insertion anchor not found')
    text=text.replace(anchor,backend+anchor,1)
deps.write_text(text,encoding='utf-8')

notices=Path('THIRD_PARTY_NOTICES.md'); text=notices.read_text(encoding='utf-8')
text=text.replace('vendored third-party frontend assets and one optional runtime-loaded HLS library','vendored third-party frontend/backend assets and one optional runtime-loaded HLS library',1)
hls='| hls.js | 1.6.16 | Apache-2.0 | HLS Widget only: pinned jsDelivr runtime URL with SRI; loaded lazily by `public/js/camera-video-streaming.js` | `licenses/hls.js-1.6.16-Apache-2.0.txt` |'
row='| PHPMailer | 7.1.1 | LGPL-2.1-only | `app/mail/vendor/phpmailer/phpmailer/` | `app/mail/vendor/phpmailer/phpmailer/LICENSE` |'
if row not in text:
    if hls not in text: raise SystemExit('THIRD_PARTY_NOTICES table anchor not found')
    text=text.replace(hls,hls+'\n'+row,1)
ref='- hls.js 1.6.16: https://github.com/video-dev/hls.js/releases/tag/v1.6.16'
if '- PHPMailer 7.1.1:' not in text: text=text.replace(ref,ref+'\n- PHPMailer 7.1.1: https://github.com/PHPMailer/PHPMailer/tree/v7.1.1',1)
notices.write_text(text,encoding='utf-8')
PY

cat > RELEASE_NOTES.md <<'EOF'
# RSS Reader Modernization 1.34.0 - Mail Send / Reply / Sent / Attachments

V1.34 extends the existing read-only IMAP Mail Widget into a bounded plain-text send/reply workflow while preserving the existing receive path and owner-scoped security boundaries.

## Added
- SMTP settings per Mail Account with SSL/TLS 465 or STARTTLS 587.
- IMAP credential reuse or separate encrypted SMTP credentials.
- From Address / optional From Name.
- SMTP connection/authentication check without sending mail.
- Plain-text Compose with To / Subject / Body.
- Plain-text Reply using Reply-To when valid, otherwise From, plus validated In-Reply-To / References threading headers.
- Sent handling modes: Auto, Server, RSS Reader.
- Auto Sent mode checks immediately, after 1 second and after a further 2 seconds before performing IMAP APPEND when no server-saved copy is found.
- Send progress indicator while SMTP/Sent handling is active.
- Outbound attachments: up to 5 files, 10 MiB per file, 20 MiB total, subject to lower PHP/Web-server limits.
- Received/Sent attachment metadata display and on-demand download from the Mail Widget.
- Bundled PHPMailer 7.1.1 subset required by the SMTP implementation, including OAuthTokenProvider interface preparation; OAuth2 authentication itself is not implemented.

## Database
Existing installations apply, in order when not already applied:
1. `026_v1_34_mail_smtp.sql`
2. `027_v1_34_mail_sent_save_mode.sql`

Both migrations are additive and idempotent by column-existence checks. Migration 026 leaves existing Mail Accounts SMTP-disabled. Migration 027 defaults existing accounts to Sent mode `auto`.

## Security / compatibility
- Existing Authentication, Session, CSRF, owner scope, input validation and IMAP SSRF/public-IP validation remain in force.
- SMTP uses the same public-address-only target validation and validated-IP pinning model as Mail IMAP.
- SMTP TLS peer/hostname verification remains enabled.
- Raw passwords, generated MIME and internal Message-ID values are not exposed through the Mail JSON API.
- SMTP success is never automatically retried because Sent verification/storage failed.
- Received attachment metadata does not include binary content; the selected part is downloaded only on demand.
- Received attachment access rechecks Widget ownership, bound Mail Account ownership, current folder, safe part identifier and bounded file size.
- Outbound attachments reject malformed uploads, dangerous executable/script extensions and clearly dangerous MIME types.

## Intentionally not included
- HTML compose.
- CC / BCC.
- Reply All / Forward.
- OAuth2 authentication implementation.
- Automatic re-attachment of files from the original received message on Reply.
- Application database storage of sent message bodies.

## Verification limits
- Production checkpoint verification confirmed SMTP send, Reply, Sent handling, outbound attachments, and received/Sent attachment display/download on the deployed hosting environment before formal release.
- The V1.34-G focused integration gate completed 605 PASS / 0 FAIL, plus PHP/JavaScript syntax, secret-scan, deterministic-package, and clean-room verification.
- The formal GitHub Release workflow reruns the repository current regression and current feature contracts on PHP 8.1 and PHP 8.4 before publishing the immutable tag.
- Provider-specific SMTP/IMAP policies remain external. Auto Sent can duplicate if a provider creates its own Sent copy only after the bounded approximately 3-second verification window; Server mode is available for such providers. Attachment delivery also remains subject to Hosting/PHP/provider size and content policies.
EOF

cat > tests/test_current_mail_v134.py <<'PY'
from pathlib import Path
root=Path(__file__).resolve().parents[1]
def read(path): return (root/path).read_text(encoding='utf-8')
version=read('app/version.php')
assert "const APP_VERSION = '1.34.0';" in version
assert "const APP_ASSET_REVISION = '1.34.0';" in version
assert (root/'database/migrations/026_v1_34_mail_smtp.sql').is_file()
assert (root/'database/migrations/027_v1_34_mail_sent_save_mode.sql').is_file()
assert read('app/mail/vendor/phpmailer/phpmailer/VERSION').strip()=='7.1.1'
api=read('public/api_v1.php')
assert all(x in api for x in ('mail.message.send','mail.message.attachments','mail.message.attachment.download'))
smtp=read('app/mail/mail_smtp_client.php'); assert 'PHPMailer' in smtp and 'getSentMIMEMessage' in smtp
assert all((root/p).is_file() for p in ('app/mail/mail_sent.php','app/mail/mail_attachment.php','app/mail/mail_received_attachment.php'))
attachment=read('app/mail/mail_attachment.php')
assert 'return 5;' in attachment
assert 'return 10 * 1024 * 1024;' in attachment
assert 'return 20 * 1024 * 1024;' in attachment
js=read('public/js/mail-widget.js'); assert '送信中...' in js and 'attachment' in js.lower()
assert '**Stable release:** `RSS Reader Modernization 1.34.0`' in read('README.md')
assert 'Release tag: `v1.34.0`' in read('README.md')
assert '## 1.34.0 - 2026-09-11' in read('CHANGELOG.md')
notes=read('RELEASE_NOTES.md'); assert notes.startswith('# RSS Reader Modernization 1.34.0') and 'Verification limits' in notes
assert not (root/'config/local.php').exists()
print('PASS: current V1.34 Mail release contract')
PY

python3 <<'PY'
from pathlib import Path
runner=Path('tests/run-current-features.sh'); text=runner.read_text(encoding='utf-8')
block='''echo '== Current feature contracts: V1.34 Mail =='
python3 "$SCRIPT_DIR/test_current_mail_v134.py"
php -l "$ROOT/app/mail/mail_account.php"
php -l "$ROOT/app/mail/mail_api.php"
php -l "$ROOT/app/mail/mail_attachment.php"
php -l "$ROOT/app/mail/mail_message.php"
php -l "$ROOT/app/mail/mail_received_attachment.php"
php -l "$ROOT/app/mail/mail_reply.php"
php -l "$ROOT/app/mail/mail_sent.php"
php -l "$ROOT/app/mail/mail_service.php"
php -l "$ROOT/app/mail/mail_smtp_client.php"
php -l "$ROOT/public/api_v1.php"
node --check "$ROOT/public/js/mail-widget.js"

'''
anchor="echo '== Current feature contracts: Account Security =='"
if 'Current feature contracts: V1.34 Mail' not in text:
    if anchor not in text: raise SystemExit('current feature runner anchor not found')
    text=text.replace(anchor,block+anchor,1)
runner.write_text(text,encoding='utf-8')
PY

printf '1.34.0\n' > .github/release-request.txt
python3 tests/test_current_mail_v134.py
for file in app/mail/mail_account.php app/mail/mail_api.php app/mail/mail_attachment.php app/mail/mail_message.php app/mail/mail_received_attachment.php app/mail/mail_reply.php app/mail/mail_sent.php app/mail/mail_service.php app/mail/mail_smtp_client.php public/api_v1.php; do php -l "$file"; done
node --check public/js/mail-widget.js
python3 tools/check_release_ready.py --release 1.34.0

rm -rf .release-sync
rm -f .github/workflows/v134-release-sync.yml
git config user.name 'github-actions[bot]'
git config user.email '41898282+github-actions[bot]@users.noreply.github.com'
git add -A
git commit -m 'Prepare V1.34.0 Mail release'
git push origin HEAD:release/v1.34.0
