<?php

declare(strict_types=1);

if (!defined('APP_REMOTE_CREDENTIAL_KEY_ID')) {
    define('APP_REMOTE_CREDENTIAL_KEY_ID', app_env('APP_REMOTE_CREDENTIAL_KEY_ID', 'primary'));
}
if (!defined('APP_REMOTE_CREDENTIAL_KEY_B64')) {
    define('APP_REMOTE_CREDENTIAL_KEY_B64', app_env('APP_REMOTE_CREDENTIAL_KEY_B64', ''));
}
if (!defined('APP_REMOTE_ALLOWED_PORTS')) {
    define('APP_REMOTE_ALLOWED_PORTS', app_env('APP_REMOTE_ALLOWED_PORTS', '21,22,443'));
}
if (!defined('APP_REMOTE_PRIVATE_NETWORK_ENABLED')) {
    define('APP_REMOTE_PRIVATE_NETWORK_ENABLED', app_env_bool('APP_REMOTE_PRIVATE_NETWORK_ENABLED', false));
}
if (!defined('APP_REMOTE_PRIVATE_NETWORK_CIDRS')) {
    define('APP_REMOTE_PRIVATE_NETWORK_CIDRS', app_env('APP_REMOTE_PRIVATE_NETWORK_CIDRS', ''));
}
if (!defined('APP_REMOTE_CONNECT_TIMEOUT_MS')) {
    define('APP_REMOTE_CONNECT_TIMEOUT_MS', max(500, min(30000, (int) app_env('APP_REMOTE_CONNECT_TIMEOUT_MS', '5000'))));
}
if (!defined('APP_REMOTE_TRANSFER_TIMEOUT_MS')) {
    define('APP_REMOTE_TRANSFER_TIMEOUT_MS', max(APP_REMOTE_CONNECT_TIMEOUT_MS, min(300000, (int) app_env('APP_REMOTE_TRANSFER_TIMEOUT_MS', '60000'))));
}
if (!defined('APP_REMOTE_TRANSFER_MAX_BYTES')) {
    define('APP_REMOTE_TRANSFER_MAX_BYTES', max(1048576, min(1073741824, (int) app_env('APP_REMOTE_TRANSFER_MAX_BYTES', '104857600'))));
}
if (!defined('APP_REMOTE_EDITOR_MAX_BYTES')) {
    // V1.30 keeps browser text editing deliberately small. Editor content must
    // never inherit the much larger general remote-transfer ceiling.
    define('APP_REMOTE_EDITOR_MAX_BYTES', max(65536, min(1048576, (int) app_env('APP_REMOTE_EDITOR_MAX_BYTES', '524288'))));
}
if (!defined('APP_REMOTE_TEMP_DIR')) {
    define('APP_REMOTE_TEMP_DIR', app_env('APP_REMOTE_TEMP_DIR', dirname(__DIR__, 2) . '/var/remote-tmp'));
}
if (!defined('APP_REMOTE_UPLOAD_MAX_REQUEST_BYTES')) {
    define('APP_REMOTE_UPLOAD_MAX_REQUEST_BYTES', max(APP_REMOTE_TRANSFER_MAX_BYTES + 65536, min(1074790400, (int) app_env('APP_REMOTE_UPLOAD_MAX_REQUEST_BYTES', (string) (APP_REMOTE_TRANSFER_MAX_BYTES + 1048576)))));
}
if (!defined('APP_REMOTE_SSH_KNOWN_HOSTS_FILE')) {
    define('APP_REMOTE_SSH_KNOWN_HOSTS_FILE', app_env('APP_REMOTE_SSH_KNOWN_HOSTS_FILE', ''));
}
if (!defined('APP_REMOTE_USER_AGENT')) {
    define('APP_REMOTE_USER_AGENT', app_env('APP_REMOTE_USER_AGENT', 'iGuguru-RemoteFiles/1.30'));
}

require_once __DIR__ . '/remote_exception.php';
require_once __DIR__ . '/remote_path.php';
require_once __DIR__ . '/remote_host.php';
require_once __DIR__ . '/remote_crypto.php';
require_once __DIR__ . '/remote_connection.php';
require_once __DIR__ . '/remote_provider.php';
require_once __DIR__ . '/remote_permission_provider.php';

require_once __DIR__ . '/remote_listing.php';
require_once __DIR__ . '/remote_curl_transport.php';
require_once __DIR__ . '/providers/curl_provider.php';
require_once __DIR__ . '/providers/ftp_provider.php';
require_once __DIR__ . '/providers/ftps_provider.php';
require_once __DIR__ . '/providers/sftp_provider.php';
require_once __DIR__ . '/providers/webdav_provider.php';
require_once __DIR__ . '/remote_provider_factory.php';

require_once dirname(__DIR__) . '/user_file.php';
require_once dirname(__DIR__) . '/file_library.php';
require_once dirname(__DIR__) . '/file_preview.php';
require_once __DIR__ . '/remote_service.php';
require_once __DIR__ . '/remote_permission_service.php';
require_once __DIR__ . '/remote_editor.php';
require_once __DIR__ . '/remote_api.php';
