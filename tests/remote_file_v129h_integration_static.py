from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
service = (ROOT / 'app/remote_file/remote_service.php').read_text(encoding='utf-8')
user_file = (ROOT / 'app/user_file.php').read_text(encoding='utf-8')
content = (ROOT / 'public/remote_file_content.php').read_text(encoding='utf-8')
preview = (ROOT / 'public/remote_file_preview_api.php').read_text(encoding='utf-8')
upload = (ROOT / 'public/remote_file_upload_api.php').read_text(encoding='utf-8')
page = (ROOT / 'public/remote-files.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/remote-files.js').read_text(encoding='utf-8')

passes = 0
fails = 0

def check(condition, label):
    global passes, fails
    if condition:
        passes += 1
        print(f'PASS: {label}')
    else:
        fails += 1
        print(f'FAIL: {label}')

check('user_file_validate_local_source' in user_file and 'user_file_store_local_file' in user_file,
      'File Library exposes server-side validation/store helpers for remote imports')
check('user_file_validate_local_source($sourcePath' in user_file,
      'remote import path reuses File Library MIME/content validation instead of trusting extension')
check('remote_service_download_temp' in service and 'APP_FILE_UPLOAD_MAX_BYTES' in service,
      'Remote to File Library download is bounded by File Library size policy')
check('user_file_store_local_file($ownerId, $path, $name, true)' in service,
      'Remote to File Library stores through owner-scoped File Library metadata path')
check('user_file_library_find_owned($ownerId, $fileId)' in service,
      'File Library to Remote resolves source file by authenticated owner')
check('user_file_library_content_is_intact' in service,
      'File Library to Remote revalidates private stored content before transfer')
check("'txt' => USER_FILE_TEXT_PREVIEW_MAX_BYTES" in service and "'csv' => USER_FILE_CSV_PREVIEW_MAX_BYTES" in service,
      'Remote TXT/CSV preview preserves existing bounded preview byte limits')
check("$extension === null || $extension === 'zip'" in service,
      'Remote ZIP remains preview-disabled')
check('@unlink($tempPath);\n    $tempPath = null;\n    exit;' in content,
      'binary preview explicitly removes temporary file before response exit')
check('@unlink($tempPath);\n        $tempPath = null;\n        remote_preview_emit(200' in preview,
      'TXT/CSV preview explicitly removes temporary file before JSON exit')
check('is_uploaded_file($tmp)' in upload and 'APP_REMOTE_TRANSFER_MAX_BYTES' in upload,
      'browser upload requires real HTTP upload temp file and remote transfer size bound')
check("form.set('csrf_token', csrfToken())" in js and "credentials: 'same-origin'" in js,
      'Remote upload sends CSRF token and same-origin credentials')
check('remote.file.import' in js and 'remote.file.export' in js,
      'UI exposes both Remote to File Library and File Library to Remote flows')
check("'library_files' => array_map" in page and 'file_stored_name' not in page,
      'initial browser state exposes File Library public metadata only, not physical stored name')
check('remote_connection_secret' not in page and 'private_key' not in page.split('$initialState', 1)[1].split('?>', 1)[0],
      'initial browser state does not include encrypted remote credential or private key material')

print(f'RESULT: PASS {passes} / FAIL {fails}')
raise SystemExit(0 if fails == 0 else 1)
