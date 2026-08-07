# V1.7-H / R2 Test report

## Scope

- RSS表示件数 auto／1～30 Validation
- Feed `widget_config`保存・再読込・Legacy NULL互換
- Feed create／update APIの不正件数拒否
- RSS追加／編集UIと保存値復元
- 実Card高を使う自動件数調整
- Search Feed表示件数との分離
- 不要な横Scrollbar抑止
- Feed／Clock／Game／Memo／Task／CalendarのOverflow Policy
- V1.7-H SQLのPrefix対応と`information_schema`非依存
- Widget Grid／Drag／Game／Timer／Swipe／Cache／Security Header／30日ログインの回帰

## Focused result

V1.7-B～V1.7-H/R2の後半回帰を現在Sourceで再実行した。

```text
PASS 401
FAIL 0
SKIP 0
```

さらにPackage作成直前に、今回直接変更したFeed Runtime／API／Dashboard Widget／V1.2-B Feed UX／V1.7-H／R2専用Testを再実行した。

```text
PASS 321
FAIL 0
SKIP 0
```

R2専用Test、V1.7-H Widget Height、Dashboard Render、Remember Token、Persistent Login、Grid Prototype、Cache／Security Headerを含む。

## Historical segmented regression

`tests/run.sh`全体は実行時間上限を超えるため、同じRunner順序を保ちながら複数区間で実行した。

- 前半区間: PASS 2,823 / FAIL 0 / SKIP 9
- 中間区間: PASS 1,452 / FAIL 0 / SKIP 2
- 後半V1.4～V1.6区間: 機能TestはFAIL 0。旧Version Label固定Testを1件検出し、R2を正当な後続Checkpointとして許可するTestへ更新後に再実行PASS。
- V1.7-B～H/R2: PASS 401 / FAIL 0 / SKIP 0

期待値を緩めた機能回避は行っていない。変更した過去Testは、後続Application Version、Feedの新しい自動件数方式、標準jQuery APIを扱えるFakeへ追従させたもの。

## Syntax

- PHP: 112 files PASS
- JavaScript: 28 non-minified files PASS

## Environment limits

- 実MySQL／MariaDB ServerへのMigration実行はこの環境では行えない。
- 本番で判明した`information_schema`権限制限を反映し、R2 SQLは同Schemaを参照しない。
- Headless Browserは過去Stageと同様に環境依存で停止する場合があるため、R2ではRuntime／DOM／CSS構造TestをRelease判断の正本とする。
