# V1.28 File Library Phase 2

V1.28 extends the authenticated File Library introduced in V1.27 without changing its metadata-only database schema or private-storage boundary.

## Added

- File Detail: original filename, MIME, extension, size, upload time, file id, and image dimensions when available. Physical stored names, owner ids, and filesystem paths are never returned.
- PDF Viewer: protected `file_content.php?id=<id>&mode=preview` for validated PDF only. The browser native PDF viewer is used; no PDF.js/CDN/server parser is added.
- TXT Preview: UTF-8 only, bounded to 64 KiB and 300 lines. UTF-8 BOM is accepted, invalid encoding fails closed, and full download remains available.
- CSV Preview: UTF-8 only, bounded to 512 KiB, 50 data rows, 30 columns, and 64 KiB per logical record. Parsing uses `fgetcsv` and cells are rendered with `textContent`.
- Responsive UI polish for actions, Modal content, long filenames/meta, and Smartphone layouts.

## Security boundary

All preview/detail access starts from the authenticated session user and an owner-scoped numeric file id. The server resolves the private path itself and revalidates the stored file before returning content. The browser cannot submit a filesystem path, stored random name, or owner id.

File response protections remain `no-store`, `X-Content-Type-Options: nosniff`, `Cross-Origin-Resource-Policy: same-origin`, and restrictive sandbox CSP. TXT/CSV dynamic content is inserted as text, not HTML. ZIP remains download-only and is never opened/extracted by the application.

## Resource limits

| Preview | Limit |
| --- | --- |
| TXT | 64 KiB / 300 lines |
| CSV | 512 KiB / 50 data rows / 30 columns / 64 KiB per record |
| PDF | Browser-native protected stream; no server parsing |

## Database / deployment

No V1.28 database migration is required. Existing V1.27 installations already using Migration `020_v1_27_user_files.sql` keep the same table contract. No new required environment variable or secret is introduced.
