from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
remote_files = (ROOT / 'public/js/remote-files.js').read_text(encoding='utf-8')
editor_js = (ROOT / 'public/js/remote-editor.js').read_text(encoding='utf-8')

checks = []

def check(condition: bool, label: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + label)

check("1.30.0-dev.4-r3" in version, 'R3 uses a distinct version/asset revision')
check("new URLSearchParams(window.location.search || '')" in remote_files, 'Remote Files reads return-state query parameters')
check("currentConnectionId: requestedConnectionId" in remote_files, 'requested connection is restored into list state')
check("currentPath: requestedPath" in remote_files, 'requested directory is restored into list state')
check("loadDirectory(state.currentPath);" in remote_files, 'valid restored state loads the requested directory')
check("&& !editorExtensionAllowed(extension)" in remote_files, 'Preview is suppressed when the file already has Edit')
check("function backUrl()" in editor_js and "params.set('remote_connection_id'" in editor_js and "params.set('path', pathParent(state.path))" in editor_js, 'Editor back URL carries connection and parent directory state')
check("el.back.href = backUrl();" in editor_js, 'Editor back button uses state-preserving URL')
check("APP_REMOTE_CREDENTIAL_KEY_B64" not in remote_files and "APP_REMOTE_CREDENTIAL_KEY_B64" not in editor_js, 'R3 browser changes do not expose credential key material')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
