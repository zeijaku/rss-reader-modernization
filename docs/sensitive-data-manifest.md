# Sensitive data manifest

Never commit or deploy beneath `public/`:

- Production database dumps (`rss.sql`, arbitrary `*.sql`, `*.dump`)
- Access/application logs (`*.log`, Legacy `dat/`)
- PHP session files (`session_file/`, `var/session/sess_*`)
- SB-13 before/after DB audit snapshots (`var/db-migration/*.json`)
- Real `.env` or secret PHP configuration
- Backups and archives (`*.zip`, `*.bak`, `*.backup`, `*.old`)
- Production database host, username, password, identity/password hashes, or copied error output

## Curated SQL exception

SB-13 intentionally versions only these reviewed, data-free/fake SQL artifacts:

- `database/schema.sql`
- `database/audit/preflight.sql`
- `database/audit/postflight.sql`
- `database/migrations/001_sb13_integrity.sql`
- `database/fixtures/sample.sql`

They contain schema, read-only audit queries, DDL, or explicit fake/example rows only. Production dump rows must never be copied into them.

The repository uses a `public/` DocumentRoot boundary. Runtime logs, sessions, throttle data, and DB migration snapshots belong under private `var/` paths and are ignored except for `.gitkeep` placeholders.
