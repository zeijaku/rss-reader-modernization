# M4-D / R1 Test Report

## 対象

GitHub Repository公開面、GitHub Actions CI、Security reporting、Contribution、Issue template、Portfolio資料と、M4-C以前のApplication regression。Application Runtimeの機能変更は行っていない。

## Source tree結果

全Runnerは実行環境の1回あたりの実行枠に合わせ、M4-C operationsまでと残りのhealthcheck / M4-D testへ分割して完走した。Checkpoint manifest追加後はRepository / path検査が3件増えている。

```text
PASS 4208
FAIL 0
SKIP 7
```

内訳:

```text
Secure Baseline〜M4-C operations: PASS 3920 / FAIL 0 / SKIP 7
M4-C healthcheck + M4-D:         PASS 288  / FAIL 0 / SKIP 0
```

## 構文

```text
PHP syntax: 71 files PASS
JavaScript syntax: 10 files PASS
Python syntax: 64 files PASS
GitHub Actions YAML structure: PASS
```

Python testは外部Packageに依存せず、Python standard libraryだけで実行できることを確認した。

## M4-D専用確認

```text
CI workflow:             PASS 38
Repository documentation: PASS 174
Public surface:          PASS 59
```

確認内容:

- `main` push / Pull Request / manual dispatchのTrigger。
- PHP 8.1 / 8.4 matrix、Python 3.12、Node.js 20。
- PHP extension: curl、mbstring、pdo_mysql、pdo_sqlite、simplexml。
- Workflow tokenは `contents: read`。
- Checkout credentialを保持しない。
- `pull_request_target`、write permission、Secret、Deploy、Release、`continue-on-error`なし。
- SECURITY.md、CONTRIBUTING.md、Bug report template。
- Repository Description / Topics / Settings / Ruleset確認手順。
- Portfolioの技術要点、ScreenshotのData除外、AI支援説明例。
- M2-GのDB / API / Security / RSS Engine / Frontend重要file Hashが不変。
- Runtime、DB、Backup、Secret、Archiveが公開Treeへ混入していないこと。

## GitHub hosted CI

Workflow定義とLocal regressionはPASS。ただしGitHub hosted runnerは、このZIPをGitHubへpushする前には実行できない。

次は利用者確認としてHOLD:

- PHP 8.1 Job
- PHP 8.4 Job
- README CI Badge
- Private vulnerability reporting
- Repository Description / Topics / Ruleset

Hosted CIの成功をLocal PASSへ読み替えない。

## ZIP再展開後

```text
PASS 4208
FAIL 0
SKIP 7
```

## Package / Manifest

```text
PASS 369
FAIL 0
```

確認内容:

- ZIP CRC、path traversal、absolute path、duplicate entryなし。
- Top-level directoryは1つ。
- 入れ子ZIPなし。
- Manifest file setとSHA-256一致。
- `config/local.php`、`.env`、実DB、Backup、Log、Session、Cache、Lock、Stateなし。
- Runtime directoryは空または`.gitkeep`のみ。
- GitHub workflow、Security、Contribution、Issue template、Portfolio資料を収録。

## SKIP

```text
SKIP: PDO SQLite integration tests (driver unavailable in this execution environment).
SKIP: live SimpleXML fixture parsing (SimpleXML/mbstring unavailable in this execution environment).
SKIP: SB-14 live parser matrix requires SimpleXML and mbstring.
SKIP: M1-A live normalized parser checks require SimpleXML and mbstring.
SKIP: M1-C live adapter matrix requires SimpleXML and mbstring.
SKIP: M1-D live identity adapter matrix requires SimpleXML and mbstring.
SKIP: Chromium runtime dependencies are incomplete for M2-F browser smoke.
```

実MySQL、実Feed、実Browser、実Restore drillはPASS扱いにせず、M4-Fへ残す。
