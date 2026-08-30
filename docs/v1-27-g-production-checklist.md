# V1.27-G Production Integration Checklist

V1.27-G is the integration/security checkpoint for V1.27 B-F. It is not the formal V1.27.0 release/tag step.

## Version state

- Formal application version: `1.26.0`
- Integration asset revision: `1.27.0-dev-g1`
- Development branch: `feature/v1.27-file-library-ui`
- Formal V1.27.0 promotion/tag/release: deferred to the next release phase

## Existing V1.27-F installation

If V1.27-F1 is already deployed and Migration 020 has already been applied:

1. Back up the application files and database.
2. Back up `var/uploads/` if it already contains files.
3. Apply the V1.27-G cumulative update ZIP at the application root.
4. Do **not** re-run Migration 020.
5. Hard reload the browser once so `1.27.0-dev-g1` assets are used.

V1.27-G itself adds no new migration and no required secret/configuration.

## Direct upgrade from formal V1.26.0

1. Back up the application and database.
2. Confirm `DB_TABLE_PREFIX` in `config/local.php`.
3. Edit `database/migrations/020_v1_27_user_files.sql` so `@table_prefix` matches production.
4. Execute Migration 020 once.
5. Apply the cumulative V1.27-G update ZIP.
6. Ensure the private upload directory exists outside `public/` and is writable by PHP. Default: `var/uploads/`.
7. Ensure PHP Fileinfo is enabled.
8. For the 10 MiB application upload limit, use PHP/web-server request limits large enough for multipart overhead. Recommended PHP values are `upload_max_filesize >= 10M` and `post_max_size >= 12M`.
9. Do not expose `var/uploads/` through a Web alias.

For fresh installs, V1.27-G integrates the same `user_file` table definition into `database/schema.sql`.

## Tracking Parameter checks

- Article URL removes the V1.27 tracking allowlist such as `utm_*`, `fbclid`, `gclid`, `msclkid`, `mc_*`.
- Normal parameters such as `id`, `page`, and article-specific parameters remain.
- Fragment (`#...`) remains.
- Registered RSS Feed URL is not rewritten by the article URL normalizer.
- Feed Health / RSS Management / SSRF-safe Feed fetch behavior remains unchanged.

## Dashboard checks

Check both PC and Smartphone widths.

- Widget headers remain vertically aligned.
- Header buttons / three-dot menus / touch targets remain usable.
- Drag handles and existing Widget Drag & Drop remain usable.
- Loading / Empty / Error presentation has no conspicuous regression.
- RSS / Search Feed / All RSS Recent / Memo / Task / Clock / Calendar / Information Board / Game Widgets still operate normally.
- No new global Ajax/fetch override or startup gate was introduced.

## File Library checks

### Upload

- JPEG / PNG / GIF / WebP / PDF / TXT / CSV / ZIP can be selected when content is valid.
- One file up to 10 MiB can be uploaded.
- More than 10 MiB is rejected.
- PHP / PHTML / PHAR / CGI / script/executable types remain rejected.
- SVG remains rejected.
- Dangerous double extensions such as `image.jpg.php` remain rejected.
- Extension/MIME mismatch remains rejected.
- ZIP is stored/downloaded only and is not extracted server-side.
- Drag & Drop selects one file; it does not auto-submit.
- Multiple files dropped at once are rejected.
- Upload button shows the loading state during submit.

### Library / ownership

- Drawer opens File Library.
- List is paginated and newest-first.
- Image thumbnails load through the authenticated content endpoint.
- Download works for the current user's file.
- Delete removes the item from the current user's Library.
- If a second account is available, changing the `id` parameter to another user's file must not expose View / Download / Delete access.
- HTML/URL does not reveal the random stored filename or physical upload path.

### Image Viewer

- JPEG / PNG / GIF / WebP thumbnail click opens the in-page Image Viewer.
- Eye button opens the same viewer instead of a new tab when JavaScript/Bootstrap are available.
- Filename is displayed.
- Loading and error states are usable.
- Close button, backdrop, and Esc close the Bootstrap modal normally.
- Smartphone view contains the image without large horizontal overflow.
- PDF / TXT / CSV / ZIP do not receive inline Image Viewer behavior.

## Security checks

- Login is required for File Library and content access.
- POST state changes require CSRF.
- File lookup/delete remains owner scoped.
- Browser-provided MIME is not trusted; Fileinfo is used server-side.
- Stored physical filename is random and remains outside `public/`.
- View/Download uses `file_content.php` rather than a physical path.
- Responses keep `X-Content-Type-Options: nosniff` and same-origin policy.
- Image inline responses keep restrictive CSP.
- No uploaded ZIP is extracted or executed.
- Public PHP deny-by-default allowlist remains enabled.

## Production verification boundary

Automated tests can verify code and security contracts but cannot fully reproduce the production Web server, PHP upload limits, filesystem ownership, browser cache, Smartphone physical interaction, or a two-account authorization test. Complete the checks above before the formal V1.27.0 release phase.
