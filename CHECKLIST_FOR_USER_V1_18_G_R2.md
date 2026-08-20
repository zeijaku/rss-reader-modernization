# V1.18.0 pre-Git R2 production checklist

## Before apply
- Backup current code, config/local.php, DB, and required var data.
- No SQL or migration is required.
- Do not overwrite config/local.php or runtime DB/data files.

## Apply
- Deploy the regenerated 1.18.0 Runtime ZIP by relative path.
- Final Asset Revision is `1.18.0-r2`, so the previous 1.18.0 candidate cache is bypassed by URL. A normal reload is sufficient; hard reload is optional for troubleshooting.

## CSRF/session check
- Normal RSS/Widget refresh and update operations should not show `CSRF validation failed.`.
- The default Session Idle Timeout is 7200 seconds (2 hours), Absolute Timeout is 43200 seconds (12 hours). The CSRF token itself has no independent timer.
- When Remember Me is valid, an already-open Dashboard should continue operating after silent Session restoration: the previous page token is accepted only for a short grace period and the page is synchronized to the fresh token from the API response.
- When Remember Me is not valid and authentication has genuinely expired, the Dashboard should return to the normal Login flow instead of leaving a raw unauthenticated API error on screen.
- If a long-idle case can be reproduced naturally, confirm that the first update after returning does not show `CSRF validation failed.`.

## Smartphone Calendar check
- On iPhone/Smartphone width, Calendar should stay inside the Card width.
- The page itself should not gain a right-side blank area or horizontal pan due to Calendar.
- All seven weekday/day columns should remain visible inside the Card.
- Long Task/Event titles may ellipsize inside narrow cells; they must not widen the page.
- On PC with a deliberately narrow Calendar Widget, any required horizontal overflow should stay inside the Calendar Card rather than widen the whole Dashboard.

## Regression smoke
- RSS refresh/update, Widget settings, D&D, Stock, Calendar month move/event edit, and Connection Monitor still work.
- Footer remains RSS Reader Modernization 1.18.0.
