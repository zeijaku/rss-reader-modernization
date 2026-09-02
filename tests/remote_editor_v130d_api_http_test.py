from pathlib import Path
import base64
import json
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
pass_count = 0
fail_count = 0

def check(condition, label):
    global pass_count, fail_count
    if condition:
        pass_count += 1
        print('PASS: ' + label)
    else:
        fail_count += 1
        print('FAIL: ' + label)


def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def request(base, method='GET', path='/remote_file_editor_api.php', body=None, headers=None):
    data = body.encode('utf-8') if isinstance(body, str) else body
    req = urllib.request.Request(base + path, data=data, method=method, headers=headers or {})
    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            return response.status, dict(response.headers), response.read().decode('utf-8')
    except urllib.error.HTTPError as error:
        return error.code, dict(error.headers), error.read().decode('utf-8')


def b64(value: str) -> str:
    return base64.b64encode(value.encode('utf-8')).decode('ascii')

with tempfile.TemporaryDirectory(prefix='v130d-r2-api-') as tmp:
    harness = Path(tmp)
    (harness / 'public').mkdir()
    (harness / 'app/remote_file').mkdir(parents=True)
    shutil.copy2(ROOT / 'public/remote_file_editor_api.php', harness / 'public/remote_file_editor_api.php')

    (harness / 'app/bootstrap.php').write_text(r'''<?php
const APP_REMOTE_EDITOR_MAX_BYTES = 64;
function app_session_start(): void { if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); } $_SESSION['user_id'] = 1; $_SESSION['csrf_token'] = 'csrf-next'; }
function app_send_private_no_store_headers(): void {}
function app_session_user_id(): ?int { return (($_SERVER['HTTP_X_TEST_AUTH'] ?? '') === 'yes') ? 1 : null; }
function app_session_is_authenticated(): bool { return app_session_user_id() !== null; }
function app_csrf_current_token(): ?string { return 'csrf-next'; }
function app_send_no_store_headers(): void { header('Cache-Control: no-store'); }
function app_csrf_is_valid(?string $value): bool { return $value === 'csrf-good'; }
function app_session_release(): void { if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); } }
function app_validate_positive_int(mixed $value): ?int { $v = filter_var($value, FILTER_VALIDATE_INT); return is_int($v) && $v > 0 ? $v : null; }
''', encoding='utf-8')
    (harness / 'app/api.php').write_text('<?php\n', encoding='utf-8')
    (harness / 'app/remote_file/remote_bootstrap.php').write_text(r'''<?php
final class AppRemoteEditorException extends RuntimeException {
    public function __construct(public readonly string $errorCode, public readonly int $httpStatus = 422) { parent::__construct('editor'); }
}
function remote_path_normalize_relative(mixed $value): ?string { return is_string($value) && str_starts_with($value, '/') && !str_contains($value, '..') ? $value : null; }
function remote_editor_read(int $u, int $c, string $p): array {
    $text = '<script>danger</script>';
    return ['path'=>$p,'name'=>basename($p),'extension'=>'txt','text'=>$text,'byte_size'=>strlen($text),'sha256'=>str_repeat('a',64),'utf8_bom'=>false,'line_ending'=>'none'];
}
function remote_editor_save(int $u, int $c, string $p, string $text, string $sha): array {
    if ($p === '/conflict.txt') { throw new AppRemoteEditorException('editor_conflict', 409); }
    if ($p === '/generic.txt') { throw new RuntimeException('secret internal source must not leak'); }
    return ['path'=>$p,'name'=>basename($p),'extension'=>'php','text'=>$text,'byte_size'=>strlen($text),'sha256'=>str_repeat('b',64),'utf8_bom'=>false,'line_ending'=>'lf'];
}
function remote_api_failure(string $op, int $uid, Throwable $e): array { return ['status'=>500,'body'=>['ok'=>false,'error'=>['code'=>'remote_operation_failed','message'=>'Remote operation failed.']]]; }
''', encoding='utf-8')

    port = free_port()
    server = subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(harness/'public')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    base = f'http://127.0.0.1:{port}'
    try:
        for _ in range(50):
            try:
                urllib.request.urlopen(base + '/remote_file_editor_api.php', timeout=0.1)
            except Exception:
                time.sleep(0.03)
            else:
                break

        auth = {'X-Test-Auth':'yes'}
        status, headers, body = request(base, path='/remote_file_editor_api.php?remote_connection_id=1&path=%2Fdanger.txt', headers=auth)
        payload = json.loads(body)
        check(status == 200 and payload.get('ok') is True, 'authenticated GET returns editor read data')
        check('<script>' not in body and '\\u003Cscript\\u003E' in body, 'GET response HEX-escapes HTML-sensitive source text')
        check('no-store' in headers.get('Cache-Control', ''), 'GET response is no-store')
        check(headers.get('Cross-Origin-Resource-Policy') == 'same-origin', 'GET response is same-origin protected')

        status, _, body = request(base, path='/remote_file_editor_api.php?remote_connection_id=1&path=%2Fa.txt')
        check(status == 401 and json.loads(body)['error']['code'] == 'unauthenticated', 'unauthenticated request is rejected')

        status, headers, body = request(base, method='PUT', headers=auth)
        check(status == 405 and 'GET, POST' in headers.get('Allow', ''), 'non-GET/POST method is rejected with Allow header')

        status, _, body = request(base, method='POST', body='x=1', headers={**auth,'Content-Type':'application/x-www-form-urlencoded'})
        check(status == 415 and json.loads(body)['error']['code'] == 'unsupported_media_type', 'POST requires application/json')

        status, _, body = request(base, method='POST', body='{}', headers={**auth,'Content-Type':'application/jsonevil'})
        check(status == 415 and json.loads(body)['error']['code'] == 'unsupported_media_type', 'JSON-like media type prefix is not accepted')

        common = {'csrf_token':'csrf-good','remote_connection_id':1,'path':'/a.php','expected_sha256':'a'*64}
        bad_csrf = json.dumps({**common,'csrf_token':'bad','text_base64':b64('x')})
        status, _, body = request(base, method='POST', body=bad_csrf, headers={**auth,'Content-Type':'application/json'})
        check(status == 403 and json.loads(body)['error']['code'] == 'csrf_invalid', 'POST save enforces CSRF')

        status, _, body = request(base, method='POST', body='{bad json', headers={**auth,'Content-Type':'application/json'})
        check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'malformed JSON fails closed')

        source = '<?php\necho "<script>";\n// 日本語\n'
        good = json.dumps({**common,'text_base64':b64(source)}, ensure_ascii=False)
        status, headers, body = request(base, method='POST', body=good, headers={**auth,'Content-Type':'application/json;charset=UTF-8'})
        payload = json.loads(body)
        check(status == 200 and payload.get('ok') is True and payload['data']['text'] == source, 'canonical Base64 POST reaches dedicated save backend with exact UTF-8 source')
        check(headers.get('X-CSRF-Token') == 'csrf-next', 'save response exposes refreshed CSRF token header')
        check('<?php' not in good and '<script>' not in good and '日本語' not in good, 'normal save JSON body contains no raw source code')

        legacy = json.dumps({**common,'text':source}, ensure_ascii=False)
        status, _, body = request(base, method='POST', body=legacy, headers={**auth,'Content-Type':'application/json'})
        check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'legacy raw text save field fails closed')

        invalid = json.dumps({**common,'text_base64':'!!!not-base64!!!'})
        status, _, body = request(base, method='POST', body=invalid, headers={**auth,'Content-Type':'application/json'})
        check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'invalid Base64 alphabet fails closed')

        noncanonical = json.dumps({**common,'text_base64':'YQ'})
        status, _, body = request(base, method='POST', body=noncanonical, headers={**auth,'Content-Type':'application/json'})
        check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'non-canonical Base64 without padding is rejected')

        empty = json.dumps({**common,'text_base64':''})
        status, _, body = request(base, method='POST', body=empty, headers={**auth,'Content-Type':'application/json'})
        check(status == 200 and json.loads(body)['data']['text'] == '', 'zero-byte text save remains supported')

        oversized = json.dumps({**common,'text_base64':base64.b64encode(b'x'*65).decode('ascii')})
        status, _, body = request(base, method='POST', body=oversized, headers={**auth,'Content-Type':'application/json'})
        check(status == 413 and json.loads(body)['error']['code'] == 'editor_too_large', 'decoded editor byte ceiling is enforced before save')

        conflict = json.dumps({**common,'path':'/conflict.txt','text_base64':b64('mine')})
        status, _, body = request(base, method='POST', body=conflict, headers={**auth,'Content-Type':'application/json'})
        payload = json.loads(body)
        check(status == 409 and payload['error']['code'] == 'editor_conflict', 'backend conflict is returned as HTTP 409')
        check('Reload before saving' in payload['error']['message'], 'conflict response instructs reload rather than force overwrite')

        missing = json.dumps({'csrf_token':'csrf-good','remote_connection_id':1,'path':'/a.php','text_base64':b64('x')})
        status, _, body = request(base, method='POST', body=missing, headers={**auth,'Content-Type':'application/json'})
        check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'missing expected SHA is rejected')

        traversal = json.dumps({**common,'path':'/../a.php','text_base64':b64('x')})
        status, _, body = request(base, method='POST', body=traversal, headers={**auth,'Content-Type':'application/json'})
        check(status == 422 and json.loads(body)['error']['code'] == 'editor_state_invalid', 'traversal path is rejected before save')

        generic = json.dumps({**common,'path':'/generic.txt','text_base64':b64('SECRET_SOURCE_CONTENT')})
        status, _, body = request(base, method='POST', body=generic, headers={**auth,'Content-Type':'application/json'})
        check(status == 500 and json.loads(body)['error']['code'] == 'remote_operation_failed', 'unexpected save exception is mapped to generic error')
        check('SECRET_SOURCE_CONTENT' not in body, 'unexpected save error never echoes source content')

        status, _, body = request(base, path='/remote_file_editor_api.php?remote_connection_id=1&path=%2F', headers=auth)
        check(status == 404, 'editor root path is not treated as a file')

    finally:
        server.terminate()
        try:
            server.wait(timeout=3)
        except subprocess.TimeoutExpired:
            server.kill()

print(f'RESULT: PASS {pass_count} / FAIL {fail_count} / SKIP 0')
raise SystemExit(1 if fail_count else 0)
