# V1.27 File Library

V1.27 adds an authenticated, owner-scoped File Library backed by private filesystem storage and metadata in the `user_file` table.

## Storage

- Default file storage: `var/uploads/`
- The directory is outside `public/`.
- Physical filenames use 32 random bytes encoded as 64 hexadecimal characters plus a canonical extension.
- Original filenames are metadata/display values only and are never used as physical paths.
- File bodies are not stored as database BLOBs.

Optional environment settings:

- `APP_FILE_UPLOAD_DIR`
- `APP_FILE_UPLOAD_MAX_BYTES`
- `APP_FILE_UPLOAD_MAX_REQUEST_BYTES`

The V1.27 application maximum is 10 MiB per file. If the environment variables are not set, the private `var/uploads/` directory and the built-in bounded defaults are used.

## Allowed types

- JPEG
- PNG
- GIF
- WebP
- PDF
- TXT
- CSV
- ZIP

The server checks the real file with Fileinfo and extension/MIME allowlists. Images receive structural validation. PDF and ZIP receive signature checks. TXT/CSV reject NUL-containing binary content. ZIP is never expanded server-side.

SVG and executable/script types are intentionally not accepted in V1.27.

## Request and authorization boundary

- Login required.
- Upload/Delete are POST + CSRF.
- View/Download/Delete resolve rows by authenticated owner and positive numeric file ID.
- Physical path and stored random filename are not accepted as request parameters.
- Public file bytes are served only through `public/file_content.php` after owner and content-integrity validation.
- Images may be served inline for the Image Viewer. Other supported files are attachment downloads.

## Existing database upgrade

Apply `database/migrations/020_v1_27_user_files.sql` once after setting `@table_prefix` to the same value as `DB_TABLE_PREFIX`.

For fresh installs, V1.27-G integrates the same table into `database/schema.sql`.

## Browser behavior

- Drawer -> File Library opens the independent Library page.
- 24 items are shown per page.
- Image thumbnails and full Image Viewer content use the authenticated content endpoint.
- File input supports normal browser selection and one-file Drag & Drop selection.
- Drag & Drop does not automatically upload; the user still presses the Add button.
