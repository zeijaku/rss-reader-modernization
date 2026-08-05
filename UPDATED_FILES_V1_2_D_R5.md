# V1.2-D / R5 変更ファイル

## Application変更

- `public/css/dashboard.css`
  - 新着BellをTitle左上へ絶対配置
  - Bell分の余白をTitleの1行目だけへ限定
  - 2行目の表示幅をTitle全幅へ復元

- `public/js/dashboard.js`
  - 新着記事のTitle Wrapperへ専用Classを付与
  - Flex幅を消費していたBellの右Margin Classを削除

## 対象テスト

- `tests/test_v12d_r5_new_bell_layout.py`
  - Chromiumで1行目と2行目の開始位置を実測
  - Bell位置、Title全幅、非新着記事、Keyboard Focusを確認

## 配布情報

- `CHANGELOG.md`
- `APPLY_NOTE_V1_2_D_R5.md`
- `CHECKLIST_FOR_USER_V1_2_D_R5.md`
- `UPDATED_FILES_V1_2_D_R5.md`
- `SOURCE_BUILD.txt`
- `SOURCE_MANIFEST.sha256`

PHP、`public/index.php`、DB、SQL、Migration、`.htaccess`、`config/local.php`には変更ありません。
