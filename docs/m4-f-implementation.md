# M4-F / R1 Implementation

## Scope

M4-EのRelease package builderを使い、Version 1.0.0のRelease Candidate `1.0.0-rc1` を作成しました。

この工程ではApplication機能、DB schema、Migration、Public API、Authentication、Session、Feed Engine、Frontend Runtime Assetを変更していません。

## Added

- RC用Version marker。
- RC deterministic ZIPと外部SHA-256。
- PHP環境Probe。
- 実環境Evidence JSON Template。
- Evidence構造とRelease GateのValidator。
- MySQL、Feed、Browser、Restore、Rollbackの検証手順。
- M4-F専用Regression。

## Release boundary

RC ZIPは次の状態です。

```text
package_status=RELEASE_CANDIDATE
publishable=no
```

`APP_VERSION = 1.0.0-rc1` ではBuilderの `final` modeを実行できません。M4-Gでexact `1.0.0`へ変更した後にFinal Packageを作り直します。

## Environment result in this build environment

このBuild環境ではPHP 8.4 CLI、Python、Node.jsを利用できました。一方、`pdo_mysql`、cURL、SimpleXML、mbstring、MySQL Serverがなく、Chromium smokeも完走しませんでした。

そのため次はPASSへ読み替えていません。

- 実MySQL接続とSchema verify
- 実RSS 2.0 / RSS 1.0 / Atom
- 実Browserの8 Theme / Responsive
- Backupから別DBへのRestore drill
- GitHub hosted CIの画面上の結果

利用者環境で `docs/m4-f-validation.md` に従ってEvidenceを記録し、全項目PASS後にM4-Gへ進みます。

## Compatibility

```text
DB migration        不要
必須設定追加         なし
Cache clear          不要
削除file             なし
```
