# SB-15 Documentation / Initial Commit Gate Test Report

Build: `Secure Baseline SB-15 / R3`
Base: `Secure Baseline SB-15 / R2`

## Scope

SB-15 R3 is the final consistency pass before the Git Initial Commit. It does not add product features.

R3 changes under test:

- PHP fallback UI defaults and new-install DB schema defaults are aligned.
  - Tab 2: `Maint`
  - Tab 4: `Observe`
  - Navbar defaults use explicit `https://` URLs in `database/schema.sql`, matching PHP defaults.
- Build-environment-specific `/mnt/data/` prefixes are removed from `docs/legacy/source-sha256.txt`.
- `APP_HASH_KEY` operational guidance now states that the key must be retained after service start and backed up safely because it is required to reproduce existing login identities.
- Visible version marker and current release documentation are updated to SB-15 R3.
- Existing SB-00〜14 regression/security tests remain green.

Existing DB rows are not modified by the schema-default cleanup. No ALTER or migration is required for an existing SB-15 R2 installation.

## Source-tree regression result

The complete test suite was executed after the R3 changes.

```text
PHP syntax: 41 files OK
PASS lines: 740
FAIL lines: 0
SKIP notices: 3
```

The three skips are unchanged environment limitations:

1. PDO SQLite integration test: PDO SQLite driver unavailable.
2. SB-12 live SimpleXML fixture parse: SimpleXML/mbstring unavailable.
3. SB-14 live parser matrix: SimpleXML/mbstring unavailable.

The build environment also does not provide `pdo_mysql` or cURL for production-like MySQL/network E2E. Those paths remain covered by fakes/static invariants plus deployment-side verification from the earlier Secure Baseline checkpoints.

## R3 consistency gate

`tests/test_sb15_docs.py` additionally verifies:

- README and `app/version.php` agree on SB-15 R3;
- PHP fallback tab names match the intended schema values;
- PHP and `database/schema.sql` contain the same explicit HTTPS navbar defaults;
- Legacy source-hash evidence contains no `/mnt/data/` build path;
- README and `docs/security.md` explain post-deployment retention and backup of `APP_HASH_KEY`;
- required SB-15 docs exist and local Markdown links resolve;
- distribution handoff notes remain excluded from the Initial Commit by `.gitignore`;
- high-signal private key/cloud key patterns are absent from the checked documentation.

`tests/test_sb13_schema_render.py` verifies that the rendered prefixed schema preserves the explicit HTTPS navbar default.

Result: PASS.

## Repository / secret gate

The repository leak scanner was re-run after the R3 changes.

Confirmed absent:

- `config/local.php`;
- real `.env`;
- `rss.sql` / `rss.zip`;
- runtime session/log/throttle/migration data;
- uncurated DB dumps/backups;
- high-signal private key/cloud API key patterns.

A temporary Git repository was initialized from the R3 tree and `git add .` was simulated.

```text
Staged files: 254
CHECKLIST_FOR_USER.md: not staged
UPDATED_FILES_SB15.md: not staged
config/local.php: ignored
README / CHANGELOG / schema / security docs: staged
```

## Product-code delta

R3 changes only fallback defaults, new-install schema defaults, documentation, versioning, and release evidence.

No Auth/API/Session/Feed parser/SSRF/XSS behavior is changed. Existing DB records are not rewritten.

## Initial Commit conclusion

The Initial Commit gate remains PASS for the Secure Baseline, subject to the final package-manifest and ZIP re-extraction verification below.

The Secure Baseline is now being prepared as the public Repository Initial Commit. License selection, third-party notices, README, and public documentation organization are publication-preparation work and do not change runtime behavior. Engine/Frontend modernization and the later v1.0 release remain separate work.

## Candidate ZIP verification

The final R3 package is generated only after documentation and manifest finalization. It is then:

1. checked with `unzip -t`;
2. extracted to a separate directory;
3. verified against `docs/package-manifest.txt`;
4. re-tested using the complete `tests/run.sh` suite;
5. re-scanned for repository/private artifacts.

Final verification result:

```text
ZIP integrity: PASS
License/third-party notice set: PASS
Manifest entries: 262
Missing/unlisted files: 0 / 0
Hash/size mismatches: 0 / 0
Extracted PHP syntax: 41 files OK
Extracted PASS lines: 740
Extracted FAIL lines: 0
Extracted SKIP notices: 3
```

The skip reasons are identical to the source-tree run.
