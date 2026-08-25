# Tag and GitHub Release Procedure

## Current formal target

- Version: `1.21.0`
- Tag: `v1.21.0`
- Release branch: `release/v1.21.0-final`

## Safety rules

- Never move or overwrite `v1.20.1` or any existing formal release tag.
- Never force-update `v1.21.0` if it already exists.
- The final tag must point to the exact commit that passed the Version 1.21 release gate.
- Production `config/local.php`, runtime data, secrets, and legacy private archives must not be added to release assets.

## Gate

The Version 1.21 release workflow performs the final release contract, full current regression, historical compatibility gates, high-signal source secret scan, deterministic Runtime / Complete Source package verification, and clean-room package checks.

Only after the gate succeeds may `v1.21.0` and the GitHub Release be created.

## Formal assets

- `rss-reader-modernization-1.21.0.zip`
- `rss-reader-modernization-1.21.0.zip.sha256`
- `rss-reader-modernization-1.21.0-complete.zip`
- `rss-reader-modernization-1.21.0-complete.zip.sha256`

After publication, `main` should be fast-forwarded to the same exact commit as `v1.21.0`.
