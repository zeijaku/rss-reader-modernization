# RSS Reader Modernization 1.35.3

V1.35.3 is a maintenance release that aligns current project documentation and centralizes revision propagation for dynamically loaded frontend assets.

## Main changes

### Documentation maintenance

- Align the README feature list, frontend dependency versions, GitHub Actions description, release workflow, and roadmap wording with the implemented repository state.
- Clearly distinguish current behavior from historical V1.0 and Milestone 4 descriptions.
- Record Gmail OAuth2, received/Sent attachment download, 24-hour trusted 2FA browsers, adjacent-month Calendar entries, Calendar partial refresh, and Remote Editor line numbers as established behavior.

### Asset revision centralization

- Keep `app/version.php` as the single release and asset-revision input.
- Derive Calendar, Camera streaming, and RSS management child asset revisions from their PHP-versioned entry scripts.
- Remove 51 copied version markers: 48 from Calendar, one from Camera streaming, and two from RSS management.
- Preserve dependency order, bounded static-asset retry, duplicate-load markers, and revision-free fallback behavior.

## Database and configuration

- No database migration is required when updating from V1.35.2.
- No required configuration, credential, dependency, or endpoint change is introduced.

## Security and compatibility

- Dynamic loaders accept only revision characters in `[A-Za-z0-9._-]`.
- Only the `v` revision is inherited; unrelated entry-script query parameters are not copied to child assets.
- No new external request, API, or application feature is introduced.

## Production verification completed

- The V1.35.3 development checkpoint was applied to the production environment without observed problems.
- Dashboard and Calendar operation were confirmed after the documentation and asset-revision changes.

## Verification limits

- Final PHP 8.1 and PHP 8.4 CI and the release workflow must pass before the immutable tag and assets are considered complete.
- Browser cache behavior can vary slightly by browser and intermediary cache configuration.

## Release assets

The release workflow publishes:

- `rss-reader-modernization-1.35.3.zip`
- `rss-reader-modernization-1.35.3.zip.sha256`
- `rss-reader-modernization-1.35.3-complete.zip`
- `rss-reader-modernization-1.35.3-complete.zip.sha256`
