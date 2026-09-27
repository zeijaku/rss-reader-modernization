# RSS Reader Modernization 1.39.0

V1.39.0 is a Maintenance / Architecture / Security Hardening release. It keeps the existing application behavior and data model while reducing coupling in Dashboard and PHP internals, tightening GitHub / Release permissions, and adding verifiable build provenance to formal release artifacts.

## Main changes

### GitHub / repository hardening

- Pin third-party GitHub Actions used by CI and Release workflows to full commit SHAs.
- Add Dependabot monitoring for the `github-actions` ecosystem so pinned Actions can be reviewed and updated deliberately.
- Keep CI read-only and retain protected-`main` controls, required PHP 8.1 / 8.4 checks, force-push prevention, and immutable Release behavior.

### Dashboard shared core

- Extract shared CSRF/session synchronization, API request, notice, response, and duplicate-request helpers from `public/js/dashboard.js` into `public/js/dashboard-core.js`.
- Keep the existing Dashboard controller wrappers and UI behavior instead of introducing a new frontend framework or API contract.
- Ensure Dashboard, Stock, and Settings entry pages load `dashboard-core.js` before `dashboard.js`.
- Fix the Stock article-actions three-dot menu regression that occurred when Stock loaded the controller without its new shared core dependency.
- Add regression coverage that scans every public PHP entry point using `dashboard.js` and requires the shared core to be loaded first.

### Compatibility fixes found during manual verification

- Fix Notification Center read / mark-all-read / hide mutations for native MySQL PDO prepares by replacing repeated named placeholders with unique timestamp placeholders.
- Keep Calendar event modal footer actions reachable when URL / Memo details are expanded by restoring the Bootstrap scrollable-modal flex boundary.
- User-side verification confirmed the Notification Center close action, expanded Calendar modal controls, and Stock article-actions menu after these corrections.

### PHP architecture / security boundaries

- Keep `app/api/content.php` as the compatibility facade while moving Content, Stock, Feed, and Reader handlers into responsibility-specific modules.
- Keep `app/reader/reader_full_text.php` as the compatibility facade while separating request validation, charset normalization, extraction/sanitization, cache, and service responsibilities.
- Preserve existing public function and class names, API action names, response formats, owner scope, validation, Reader behavior, and cache behavior.
- Keep `app/http_fetch.php`, the Reader image proxy, database schema, and the established SSRF / TLS / redirect / DNS-pinning security boundary unchanged.

### Release supply-chain hardening

- Default the Release workflow to `contents: read`.
- Split the workflow into a verification/attestation job and a final publication job.
- Grant `contents: write` only to the publication job; the verification job receives only the additional OIDC / attestation permissions required to mint provenance.
- Generate GitHub Artifact Attestations for both the Runtime ZIP and Complete Source ZIP.
- Transfer verified assets between jobs through GitHub Actions artifacts, then re-check both SHA-256 sidecars and both attestations before tag / GitHub Release publication.
- Retain main-SHA revalidation, immutable-tag checks, secret scan, deterministic package verification, and Runtime / Complete Source clean-room checks.
- Document `gh attestation verify` so downloaded formal ZIPs can be independently checked against this repository.

## Database upgrade

No database migration is required for V1.39.0.

Existing RSS, Stock, Calendar, Notification, Reader, Mail, user, and Dashboard data remain unchanged.

## Configuration

No new required application setting, credential, or Runtime external dependency is introduced.

The GitHub Release workflow uses GitHub-provided OIDC / Artifact Attestation capabilities only during release automation; this does not add a Production runtime dependency.

## Security and compatibility

- Existing authentication, session, owner scope, CSRF, SSRF, XSS, SQL/PDO, validation, path confinement, credential protection, and Reader security boundaries remain in place.
- The Dashboard core split preserves the existing API endpoint and controller-facing contracts.
- The PHP responsibility split preserves existing API and Reader facade entry points.
- The Notification Center SQL correction keeps owner scoping and mutation semantics unchanged.
- The Release workflow separates build verification from repository write access and verifies provenance before publication.
- No database schema, migration, public endpoint, or Production deployment mechanism is added or changed.

## Verification completed

- Each V1.39 phase passed the repository CI on PHP 8.1 and PHP 8.4 before integration.
- A was integrated first, followed by B, C, and D; after each merge the updated `main` branch passed PHP 8.1 / 8.4 CI before the next phase was integrated.
- B was re-tested after merging A into the branch, including the Dashboard shared-core entry-point dependency coverage.
- C was re-tested on top of A+B, with the overlapping security test explicitly merged so both Dashboard-core and recursive PHP-module coverage remain active.
- D was re-tested on top of A+B+C.
- Dedicated V1.39-C architecture/security and facade runtime tests verify the split PHP modules while retaining the pre-existing contracts.
- Release workflow tests verify least-privilege job permissions, full-SHA Action pinning, SHA-256 revalidation, provenance generation, provenance verification order, and Release documentation.
- A real GitHub Artifact Attestation smoke test successfully created Sigstore-backed provenance and verified it with `gh attestation verify`.
- A second cross-job smoke test successfully uploaded an attested artifact, downloaded it in another job, verified its SHA-256, and verified the downloaded subject's attestation.
- Temporary smoke workflows were removed after verification.
- Current integrated CI continues to run the complete regression gate on PHP 8.1 and PHP 8.4.

## Verification limits

- The formal `v1.39.0` tag, Release assets, and production Release attestations are not complete until the final Release workflow succeeds on the release-ready `main` commit.
- The CI environment does not provide MariaDB server tools for the dedicated real-server mutation test, so that existing test remains skipped there; native-PDO placeholder behavior is additionally guarded by static/current-contract coverage and representative user-side verification.
- GitHub currently emits a Node.js 20 deprecation warning for the pinned `actions/upload-artifact@v4` / `actions/download-artifact@v4` commits while executing them with the platform's newer Node runtime. The tested V1.39-D flows complete successfully; a major Action update is intentionally left as a separate dependency-maintenance change.
- Release workflow verification covers repository, package, checksum, provenance, and clean-room behavior; it does not automatically deploy the formal package to Production.
- Production remains a separate deployment step and is not modified by the formalization process.

## Release assets

The Release workflow publishes:

- `rss-reader-modernization-1.39.0.zip`
- `rss-reader-modernization-1.39.0.zip.sha256`
- `rss-reader-modernization-1.39.0-complete.zip`
- `rss-reader-modernization-1.39.0-complete.zip.sha256`

Both ZIP files receive GitHub Artifact Attestations. Consumers with GitHub CLI can verify the downloaded ZIPs with `gh attestation verify ... --repo zeijaku/rss-reader-modernization`.
