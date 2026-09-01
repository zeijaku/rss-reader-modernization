# V1.29.0 production verification checklist

1. Back up application code, `config/local.php`, database, File Library storage and private runtime data.
2. Apply `database/migrations/021_v1_29_remote_connection.sql` once after matching `@table_prefix` to `DB_TABLE_PREFIX`.
3. Generate/configure `APP_REMOTE_CREDENTIAL_KEY_B64`; strict Base64 decoding must produce 32 bytes. Preserve this key after credentials are registered.
4. Configure `APP_REMOTE_TEMP_DIR` outside `public/`, allowed Remote ports and only the required private CIDRs.
5. For SFTP, configure a verified `APP_REMOTE_SSH_KNOWN_HOSTS_FILE`.
6. Run `php tools/remote_file_env_check.php` and resolve required protocol/extension failures.
7. Deploy without overwriting private configuration, File Library uploads, private keys, known_hosts or runtime-private data.
8. Confirm the visible application version reports `RSS Reader Modernization 1.29.0`.
9. Open `/remote-files`, register a non-critical test connection and run Connection Test.
10. In a test Base Path verify list/navigation, mkdir, TXT upload, refresh, preview, download, rename/move and delete.
11. Verify Remote -> File Library and File Library -> Remote, then compare file content/hash.
12. Test Japanese/space-containing filenames if the production protocol/server supports them.
13. For SFTP verify host-key mismatch rejection; for FTPS/WebDAV verify untrusted certificate rejection.
14. If private-network access is enabled, verify a target outside the configured CIDR remains denied.
15. Confirm another authenticated user cannot access the connection/file ids of the first user.
16. Confirm Browser Console and PHP/Web server logs contain no new errors or credential material.

Plain FTP is not encrypted on the wire. Use FTPS/SFTP/WebDAV where the target supports them.
