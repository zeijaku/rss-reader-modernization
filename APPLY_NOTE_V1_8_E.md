# V1.8-E / R1 Apply Note

## 前提

- V1.8-D / R1 適用済みの作業ツリーへ上書きしてください。
- DB Migration / SQL実行 / config変更はありません。
- GitHubへのCommit / Pushは、このZIP適用だけでは行いません。

## 変更概要

- Stock一覧をLegacyのランダム色4列Cardから1列Compact Listへ変更。
- Stock URLからDomainを表示（表示上は先頭 `www.` を省略）。
- Stock一覧で既存Article Actions Menuを再利用。
  - URLをコピー
  - Xへ投稿
  - Taskへ追加
  - Stock解除
- Stock画面では「Stockへ保存」を非表示にし、「Stock解除」を表示。
- Stock解除は従来どおり `stock_flag=1` の論理削除、Ownership / CSRFを維持。
- StockからTaskへ追加する場合、所有Task Widgetが1個なら直接追加、複数なら追加先選択Modalを表示。
- Task Widget 0件時はNoticeのみ表示。

## DB変更

なし。

## 確認

```powershell
php -l .\app\dashboard_widget.php
php -l .\public\index.php
php -l .\app\version.php
node --check .\public\js\dashboard.js
python .\tests\test_v18e_stock_ui_static.py
php .\tests\test_v18e_stock_task_targets.php
php .\tests\test_v18e_stock_render.php
```

詳細は `docs/v1-8-e-implementation.md` と `docs/test-report-v1-8-e.md` を参照してください。
