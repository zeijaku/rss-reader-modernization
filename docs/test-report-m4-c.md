# M4-C / R1 Test Report

## 対象

新規設置、更新、設定、Backup、Restore、Rollback、配置Checklistと、設定exampleのRuntime整合。Application Runtimeの機能変更は行っていない。

## Source tree結果

```text
PASS 3837
FAIL 0
SKIP 7
```

Checkpoint manifest作成前の全回帰はPASS 3834。Manifest追加後はRepository / path検査が3件増え、最終値はPASS 3837。

## 構文

```text
PHP syntax: 71 files PASS
JavaScript syntax: 10 files PASS
Python test compile: PASS
```

## M4-C専用確認

```text
Configuration inventory: PASS 117
Operations documentation: PASS 161
Healthcheck contract: PASS 17
```

確認内容:

- Runtime設定Key、Default、制約とexample / Documentation。
- Environment variable > local.php > defaultの優先順位。
- dotenv loaderを持たないことの明記。
- APP_HASH_KEY継続、Table prefix一致、Database backup必須。
- 新規設置、Legacy migration、Git / ZIP update。
- Code-only rollbackとDB migration rollbackの分離。
- healthcheckがDB接続を行わないことの明記。
- Backup file Size / SHA-256 / Restore drill。
- Runtime Session / Cacheを永続Backupへ混ぜない方針。
- 危険なforce push、hard reset、全体再帰削除、chmod 777を実行例へ出さないこと。
- Markdown local link、Secret / Runtime / ZIP安全検査。
- M4-Aで固定したDB / API / Security / Feed Engine / Frontend Asset hash。

## ZIP再展開後

```text
PASS 3837
FAIL 0
SKIP 7
```

## Package / Manifest

```text
PASS 351
FAIL 0
```

確認内容:

- ZIP CRC、path traversal、absolute path、duplicate entryなし。
- Top-level directoryは1つ。
- 入れ子ZIPなし。
- Manifest file setとSHA-256一致。
- `config/local.php`、`.env`、実DB、Backup、Log、Session、Cache、Lock、Stateなし。
- Runtime directoryは空または`.gitkeep`のみ。
- 高確度な秘密鍵 / AWS key / API key patternなし。
- Installation / Update / Configuration / Backup / Restore / Rollback資料を収録。

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

実MySQL、実Feed、実Browser、実Restore drillはPASS扱いにせず、M4-Fの確認項目として残す。
