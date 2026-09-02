# V1.30-G Production Integration Checklist

V1.30-G is the production/integration checkpoint for V1.30 A-F. It is **not** the formal `v1.30.0` tag/release step.

## Version state

- Formal baseline: `v1.29.0`
- Formal baseline commit: `b6a3039f6af003f247640d7926ffffab548b825e`
- Production checkpoint: `1.30.0-dev.7`
- Development branch: `feature/v1.30-remote-text-editor`
- Formal `v1.30.0` promotion/tag/release: deferred to V1.30-H

## Existing V1.30-F installation

If V1.30-F (`1.30.0-dev.6`) is already deployed and production verification passed:

1. Back up the application files.
2. Do **not** replace `config/local.php`.
3. Do **not** change `APP_REMOTE_CREDENTIAL_KEY_B64`, Remote passwords, private keys, passphrases or `known_hosts`.
4. Deploy the G checkpoint `app/version.php`.
5. Hard reload once and confirm `RSS Reader Modernization 1.30.0-dev.7`.
6. No database migration is required.
7. No provider or Remote credential change is required.

The G package also includes documentation/tests for release verification; those files do not need to be deployed to production.

## Direct cumulative update from formal V1.29.0

A cumulative V1.30-G ZIP is provided for integration/review. Before using it:

1. Back up the application.
2. Keep the production `config/local.php` and all real credential/key material unchanged.
3. Apply only repository files from the cumulative package at the application root.
4. Ensure the private Remote temporary workspace exists outside `public/` and is writable by PHP. Default: `var/remote-tmp/`.
5. If `APP_REMOTE_TEMP_DIR` is configured, that configured private path takes precedence over the default.
6. Hard reload once so `1.30.0-dev.7` assets are used.
7. No database migration is required by V1.30.

## Remote Text Editor checks

- Editable extensions remain: `txt`, `md`, `csv`, `json`, `xml`, `html`, `htm`, `css`, `js`, `php`, `ini`, `conf`, `yml`, `yaml`.
- Binary/unsupported types are rejected.
- Maximum editor content remains bounded by `APP_REMOTE_EDITOR_MAX_BYTES` (default 512 KiB).
- UTF-8 is required; Shift_JIS/EUC-JP conversion is not attempted.
- LF / CRLF and UTF-8 BOM are preserved where supported.
- Mixed EOL and CR-only inputs fail closed.
- Save uses SHA-256 optimistic conflict detection.
- Conflict never exposes a force-overwrite path.
- Local text remains in the browser after conflict until the user explicitly reloads/discards it.
- Save transport remains Base64 inside authenticated same-origin JSON POST to avoid raw-source WAF false positives.
- CSRF, authentication, owner scope, no-store and same-origin protections remain required.

## Remote Files checks

- Edit is shown only for editor-eligible text types.
- Preview remains for supported non-editor preview types such as PDF/images.
- Returning from Editor restores the same Remote connection and parent directory.
- Action icons remain differentiated by shape/title/aria-label plus color.
- File-type icons remain differentiated by shape/color while the filename remains visible.
- Smartphone/coarse-pointer controls remain usable.

## Remote transport checks

No provider implementation changes are introduced by V1.30-G.

- FTP: existing RNFR/RNTO move semantics.
- explicit FTPS: FTP operations over FTPS transport.
- SFTP: existing remote rename semantics.
- HTTPS WebDAV: MOVE with explicit Overwrite header.

The staged save strategy is not described as atomic across protocols. No remote locking is claimed. If a server rejects overwrite-rename, do not delete the original as a workaround.

## Security / deployment hygiene

The production/cumulative package must not contain:

- `config/local.php`
- real `APP_REMOTE_CREDENTIAL_KEY_B64` values
- private keys or passphrases
- `known_hosts` production content
- logs
- cache/session files
- database dumps
- runtime Remote temporary files
- user uploads

`var/remote-tmp/.gitkeep` is allowed; runtime contents under that directory are not.

## V1.30-H boundary

V1.30-H performs the final regression and release promotion:

- `tests/run-current.sh`
- `tests/run-current-features.sh`
- PHP 8.1 / PHP 8.4 release regression
- final secret/public-package scan
- clean-room package verification
- final `1.30.0` version promotion
- PR/main merge as applicable
- immutable `v1.30.0` tag
- GitHub Release

Do not create or overwrite `v1.30.0` during G.
