# Development checkpoint: 1.40.1-dev.4

Widget header presentation checkpoint, retaining dev1–dev3 improvements. See [scope, installation and checks](docs/v1.40.1-dev4-widget-headers.md). This is not the formal V1.40.1 release; the stable V1.40.0 release notes follow below.

# RSS Reader Modernization 1.40.0

V1.40.0 is a Game Widget feature release that preserves the existing Dashboard and game engines while adding individually loaded games and local word dictionaries.

## Main changes

- Reuse the existing `widget_type=game` / `widget_config.game` model, owner-scoped APIs, size/style settings and multiple placement. No new table, framework or runtime dependency.
- Add **Maze Chase**: original maze, pellets, simple enemy behavior, collisions, Score, Game Over, restart/pause, focused keyboard movement and touch buttons.
- Add **Falling Blocks**: seven block shapes, movement/rotation, soft/hard drop, line clearing, Score/Level, restart/pause and keyboard/touch controls.
- Add **Word Tiles** for English and Japanese: board/rack, horizontal/vertical words, fixed local validation, solo scoring, restart and browser saved progress. English/Japanese are separate Game options; switching between them preserves each board, rack, bag, pending turn, Score and Best.
- Shared controls provide restart/pause/expanded view/Score/Best and lifecycle boundaries. The new game modules and dictionaries load only when configured. Game keys are consumed only within the focused board, and animation stops when suspended.
- Fix **Wire Defense** settings refresh to recreate its playable Canvas. Clean removed/replaced/type-changed runtime animation, card/global listeners and the removal observer. Best remains stored; settings refresh returns to the ready/Start screen as with the prior full-page reload behavior.
- Retain Icon Quest, Lights Out, Wire Defense, Block Collapse, Cursor Field, 2048 and Reversi/Othello without rewriting their game mechanics.

## Dictionaries and licenses

- English: 43,127 filtered words from SCOWL 2020.12.07, with the upstream license/notice in `licenses/word-tiles-scowl-Copyright.txt`.
- Japanese: 40,000 filtered JMdict readings, normalized to hiragana and deduplicated. The derived wordlist is **CC BY-SA 4.0**, with EDRDG attribution and legal/redistribution notices included in `licenses/` and a public dictionary notice linked in the game.
- Dictionary source/filter metadata and deterministic build checks remain in Complete Source. The giant upstream JMdict archive is not part of Runtime or Complete packages. Word validation never calls an AI or external dictionary API.
- Application/game code remains under its existing MIT license; dictionary terms apply to the respective derived data.

## Upgrade and compatibility

Existing 1.39.2 installations and V1.40 development checkpoints require **no database migration and no new required configuration**. Do not run Fresh Install `database/schema.sql` over an existing database. Back up application files, config, database and private runtime data before updating.

For a tested complete dev.7 installation, the finalization diff only updates the release marker (plus documentation). To update directly from 1.39.2, use the full verified Runtime package or the cumulative application diff. Preserve `config/local.php`, private data and the existing database.

Score and Word progress are browser-local and scoped to user/widget/game; there is no cross-device synchronization, ranking or server score history. Widget deletion removes its Word language records; changing to an unrelated game keeps the previous reset policy. Already erased progress cannot be reconstructed.

## Verification completed

- Current regression includes owner/config/security/API validation, new game logic, English/Japanese normalization and inventory conservation, scoped storage and Wire teardown.
- Dedicated desktop/touch-emulation browser suites passed 786 checks on dev.7: production-form settings/Wire 218, shared/legacy Game 227, English Word 151 and Japanese Word 190; formal Version retains these game/runtime bytes.
- Final source readiness, PHP/asset contracts, local Current gate and the standard PHP 8.1/8.4 CI are checked before main integration. The Release workflow reruns both PHP gates and verifies secret exclusion, Runtime/Complete manifests, SHA-256, clean-room extraction and provenance before publication.

## Verification limits

- Browser tests use Chrome Headless Shell at desktop width 1280 and touch emulation width 360, with production scripts/forms and mocked same-origin HTTP responses. Physical iPhone/Safari and real production behavior remain deployment checks; no production database or credentials are touched by tests.
- Three existing local generic browser smoke checks skip when standard Chromium/Python Playwright is unavailable. All dedicated game browser suites run without skips. CI/Release use the repository's standard gate.
- Word Tiles is an initial solo word game with fixed dictionaries, no premium-square system and no multiplayer rules. Japanese matches normalized readings present in the filtered list, not every dictionary word.
- Stock is the saved-article view and does not display Game Widgets. Additions originating there target Dashboard tab 1; its pre-existing unavailable Wire Defense menu option remains outside this release's fixes. Use Dashboard to add Wire Defense.

## Release assets

- `rss-reader-modernization-1.40.0.zip` — Production Runtime
- `rss-reader-modernization-1.40.0.zip.sha256`
- `rss-reader-modernization-1.40.0-complete.zip` — Complete Source / tests / build tools
- `rss-reader-modernization-1.40.0-complete.zip.sha256`

Tag: `v1.40.0`. Tag/Release and assets are published only by the verified common Release workflow; existing release tags are never moved.
