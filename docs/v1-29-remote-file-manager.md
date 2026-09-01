# V1.29 Remote File Manager

Version 1.29 adds authenticated Remote file operations for FTP, explicit FTPS, SFTP and HTTPS WebDAV. The UI and service contract are shared, while protocol-specific behavior remains isolated under `app/remote_file/providers/`.

## Operations

- Connection registration/list/edit/delete and read-only Connection Test
- Directory listing/navigation and refresh
- Upload/download
- mkdir
- rename/move
- file/directory delete
- Remote -> File Library
- File Library -> Remote
- bounded Image/PDF/TXT/CSV preview

Remote text editing and SCP/SMB/NFS/S3/cloud-drive support are intentionally outside this release.

## Storage and credentials

Existing databases add `remote_connection` through `database/migrations/021_v1_29_remote_connection.sql`. Credentials are serialized server-side and stored only as an authenticated AEAD envelope. The deployment key is not stored in the database and must not be committed.

Generate the key with `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`. Strict Base64 decoding must produce exactly 32 bytes. Once Remote credentials are stored, changing/losing that key makes those credential envelopes undecryptable.

## Network boundary

Public IP targets are allowed by default. Private/LAN access requires administrator opt-in plus an explicit CIDR allowlist and per-connection opt-in. Loopback/link-local and other blocked address classes stay denied. DNS answers are checked on every operation and transport uses validated/pinned addresses where supported. WebDAV redirects are manual, same-origin and Base Path confined.

SFTP uses verified known_hosts host-key validation. FTPS/WebDAV retain peer/hostname TLS verification. Plain FTP remains available for legacy servers but is visibly identified as unencrypted.

## Path and transfer boundary

Browser paths stay relative to a per-connection Base Path. Segment normalization rejects traversal/control forms. Symlink/unknown entries are refused when listing metadata exposes them; server-side FTP/SFTP root/chroot remains recommended. Transfers are bounded and stream through output/input/private temporary storage rather than unbounded in-memory buffers.

## File Library integration

Remote imports are validated through existing File Library MIME/content rules before metadata/storage. File Library exports start from authenticated owner-scoped file ids and revalidate private content before transfer. Physical stored names and filesystem paths never become browser-controlled parameters.
