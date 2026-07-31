# SB-00 to SB-02 implementation record

## SB-00

- Recorded SHA-256 hashes of all primary evidence.
- Recorded the complete Legacy tree with byte sizes.
- Preserved Legacy sources outside the modified application tree.

## SB-01

- Introduced `public/` as the only web-exposed directory.
- Moved PHP common code to `app/common/`.
- Removed Legacy `dat/` and web-root session storage from the distributable tree.
- Replaced embedded configuration with environment lookups.
- Added `.env.example` containing dummy values only.
- Added deny rules and disabled directory indexing/MultiViews.
- Disabled custom access logging by default; enabled logs use a private configurable path.
- Replaced database exception body disclosure with a generic exception and private `error_log` entry.

## SB-02

- PDO uses exception mode, associative fetch mode, native prepares and `utf8mb4` for MySQL.
- All INSERT/SELECT/UPDATE statements use bound parameters.
- User and user-configuration creation is transactional.
- New timestamps use `Y-m-d H:i:s` in Asia/Tokyo.
- Feed cards order by `content_id ASC`; stock orders by `stock_id DESC`.
- Existing public function names are retained to reduce the change surface before later phases.

### Error boundary

`app/bootstrap.php` disables browser error display when `APP_DEBUG=false`, logs diagnostics privately and emits a generic HTTP 500 body for uncaught failures. Production must keep `APP_DEBUG=false`.
