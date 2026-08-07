# V1.7-B / R1 Test Report

## Baseline

- Version 1.6.0 Complete ZIP
- SHA-256: `e100df523dc889786e043506bb1f89ae21262eb56c2997b5e756903470b003e7`

## Result

```text
PASS 270
FAIL 0
SKIP 1
```

### PASS

- V1.7-B専用Version／Metadata／Migration未追加確認: 14
- Swipe、Lights Out、Storage、Game subtype、Clock Timer Runtime: 114
- Repository秘密情報／Runtime Data除外: 14
- Frontend Asset Inventory: 115
- Smartphone Swipe Browser Test: 13

### SKIP

- `tests/test_m4d_repository_docs.py`
- 理由: M4-D時点のVersion／README Markerを固定確認する歴史的Checkpoint Testであり、現在のV1.7-B Markerとは両立しないため、V1.7-Bの回帰集計から除外。

## Baseline差分確認

Application Runtimeの変更は`app/version.php`だけです。次はVersion 1.6.0 Baselineと一致しています。

- `database/`
- `config/`
- `.htaccess`
- `public/.htaccess`
- `app/api.php`
- `app/session.php`
- `public/index.php`
- Dashboard／Clock Timer／Icon Quest／Lights OutのCSS・JavaScript

DB、Migration、SQL、API、設定、外部Libraryの変更はありません。
