# V1.3-D Test Report

## Test Level

Feature

## 選択理由

変更自体は小規模ですが、通常RSSとSearch Feedの記事行、全Widget見出し、PC／Touch端末で共通利用されるCSSへ影響するため、Quickより広い関連回帰を実行しました。

## 実施結果

- Feature／関連回帰: PASS 1,098 / FAIL 0 / SKIP 0
- PHP構文: PASS 99 / FAIL 0
- JavaScript構文: PASS 19 / FAIL 0
- `tests/run.sh`構文: PASS 1 / FAIL 0
- ZIP／Manifest検証: PASS 14 / FAIL 0
- 再展開後Focused Test: PASS 94 / FAIL 0 / SKIP 0
- 再展開後PHP構文: PASS 99 / FAIL 0
- 再展開後JavaScript構文: PASS 19 / FAIL 0

## 主な確認内容

- Bootstrap既定Cell paddingが残らないこと
- 記事Title余白`7px 2px 7px 6px`
- PC三点リーダー36px × 44px
- Touch三点リーダー44px × 44px
- 三点リーダーの列中央配置
- 新着Bellと2行Title
- 通常RSS／Search Feed／Clock／Memo／Task／Calendar見出し
- 長いWidget名のEllipsis
- Widget Header高44px
- RSS概要操作
- 記事Actions
- V1.3-B Drawer
- V1.3-C Header
- 8テーマ × Dark／Primary／Light × 360／420／1024px

## 実施していないテスト

- Full回帰テスト
- 実MySQL接続
- 実RSS／Atom外部取得
- 配置先Serverでの実操作
- Version 1.3正式Release Gate

Full回帰はV1.3-Eで1回実施します。
