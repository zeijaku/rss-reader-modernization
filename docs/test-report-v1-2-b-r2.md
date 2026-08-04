# V1.2-B / R2 Test Report

## Result

- PASS: **3,862** explicit assertions
- FAIL: **0**
- SKIP: **10** when the standard runner's historical release-gate skips are included

The long regression suite was executed in the same order as `tests/run.sh`, divided into four bounded sections because a single command exceeds the execution time limit. No active test section was omitted.

## R2 focused checks

- Smartphone viewportでArticle rowが3 Cellになること。
- DOM／visual orderがStock、Title、Summaryであること。
- Stock Buttonが左端へ戻ること。
- Summary Toggleが右端で44px以上のTouch targetを持つこと。
- Unicode `▽`が直接表示されること。
- `▽`のSizeが16px以上で、透明・`display:none`ではないこと。
- SummaryなしのDisabled状態が操作不能であること。
- Summary開閉、Plain Text安全化、元記事Linkが維持されること。
- Stock URL／Full Titleが維持されること。
- 個別更新、失敗時保持、再試行、NEW件数更新が維持されること。
- Drag操作とArticle ButtonのPointer eventが競合しないこと。

## Regression coverage

- PHP／JavaScript syntax
- Secure Baseline SB-00～15
- Authentication／Session／CSRF／Authorization
- SSRF／XSS／Validation／PDO static checks
- RSS 2.0／RSS 1.0／Atom
- Feed Cache／ETag／Last-Modified／304／Retry／Backoff／stale-if-error
- M2 Frontend／Accessibility／Responsive／Runtime
- V1.1 Tracking Parameter／NEW／Widget／Clock／Memo／Task／Calendar／Account Settings
- V1.2-A Authentication／Notice／Common Error
- V1.2-B Title Tooltip／Summary Accordion／individual refresh

## SKIP reasons

- PDO SQLite Driver unavailable: 1
- SimpleXML／mbstring unavailable: 5
- M2-F Chromium runtime dependency check: 1
- Historical M2-G／M4 Version 1.0 gates: 2
- Historical V1.1-K final gate: 1
