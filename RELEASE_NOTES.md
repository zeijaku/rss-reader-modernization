# RSS Reader Modernization 1.37.0

V1.37.0 expands the existing Game Widget with 2048 and Reversi while keeping the current Dashboard architecture, database model, security boundaries, and lightweight browser-local execution model.

## Main changes

### 2048

- Add a 4x4 2048 Game Widget subtype.
- Support Arrow Key / WASD on PC and pointer-swipe controls on Smartphone.
- Show Score and browser-local Best Score.
- Add New Game and Restart actions.
- Add a short directional movement animation so tile movement is easier to follow.
- Disable the movement animation when the browser requests reduced motion.
- Keep gameplay local to the browser with no external network request.

### Reversi

- Add an 8x8 Reversi Game Widget subtype.
- Use Player as Black and a local CPU as White.
- Implement legal-move validation, eight-direction disc flipping, pass handling, game-over detection, stone counts, active-turn display, and Restart.
- Support PC click and Smartphone tap on the same responsive board.
- Use a lightweight CPU heuristic based on corner / positional value, flip count, opponent mobility, and current stone balance rather than deep Minimax search.
- Use a flat Bootstrap-derived board and disc treatment so Reversi matches the existing Dashboard and Game Widget visual language.

## Database upgrade

No database migration is required for V1.37.0.

The existing Game Widget configuration schema and `dashboard_widget` storage model are reused for both new game subtypes.

## Security and compatibility

- Existing authentication, session, owner scope, CSRF, validation, output escaping, Dashboard widget storage, and Stock integration boundaries remain in place.
- 2048 and Reversi do not add external network requests, server-side game services, required configuration, credentials, or external dependencies.
- Existing Game Widget subtypes and existing Dashboard data remain compatible.
- Reversi game state remains browser-memory only. 2048 Best Score uses browser-local storage with the existing local/session/memory fallback strategy.

## Verification completed

- 2048 production verification completed through the V1.37 development checkpoints, including PC / Smartphone controls and the lightweight directional animation.
- Reversi production verification completed through the V1.37 development checkpoints, including gameplay, local CPU response, and the Dashboard-consistent flat visual style.
- 2048 contract and runtime tests cover board movement, merge rules, score handling, game-over conditions, swipe direction, storage behavior, and visual-animation contracts.
- Reversi contract and runtime tests cover the standard initial board, legal moves, illegal-move rejection, flipping, stone counts, corner preference, pass / end-state behavior, responsive UI contracts, and flat visual treatment.
- Current CI runs the complete regression gate on PHP 8.1 and PHP 8.4.

## Verification limits

- Final PHP 8.1 and PHP 8.4 CI and the Release workflow must pass before the immutable tag and release assets are considered complete.
- The Reversi CPU is intentionally lightweight and does not perform deep game-tree search.
- Browser-local game state is not synchronized between devices.
- The Release workflow does not automatically deploy the formal package to the production environment.

## Release assets

The Release workflow publishes:

- `rss-reader-modernization-1.37.0.zip`
- `rss-reader-modernization-1.37.0.zip.sha256`
- `rss-reader-modernization-1.37.0-complete.zip`
- `rss-reader-modernization-1.37.0-complete.zip.sha256`
