# V1.7-F Test Report

## Focused regression

```text
PASS 410
FAIL 0
SKIP 0
```

対象はRemember Token Backend、Persistent Login、Session、Authentication、Logout、Account Settings、HTTP Cache／Security Header、Asset URL、Login Layout、Swipe、Lights Outです。

## Full runner

`tests/run.sh`はBrowser系を含む長時間実行のため区間分割しました。Secure BaselineからV1.7-Fまで各区間へ到達し、機能上のFAILは0でした。実行上限による再開と過去TestのVersion固定修正が入ったため、重複しない総PASS数はFocused Regressionと分けています。

## Syntax

- PHP: 110 files PASS
- Non-minified JavaScript: 7 files PASS

## Environment limitation

MySQL／MariaDB Serverがないため、Migration 007の実Database適用はV1.7-Eと同様に未実施です。V1.7-F適用前にPreflight／Migration／Postflightを実環境で確認してください。

## Manual scope

- Browser DevToolsでHttpOnly／SameSite／Secure／Expiresを確認
- Session期限切れ相当後の自動Login
- Logout後の再アクセス
- 複数端末を想定したPassword変更後の失効
