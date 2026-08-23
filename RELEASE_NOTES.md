# RSS Reader Modernization 1.20.0 Release Notes

## 目的

Version 1.20.0は、V1.19.0を正式BaselineとしてV1.20-B〜Eの機能を統合し、V1.20-F RC1で全体Regressionと本番確認を行った後に正式化するReleaseです。

## 主な変更

- **Card Header Compact**: Dashboard Widget Headerを40pxへ整理。RSS／Search Feedのtable headerも各Layerを40pxへ揃えます。
- **RSS Typing**: 通常RSS titleを使う60秒Typing Gameを追加。Japanese IME、Score／Best、hidden-tab pause、Browser storage fallbackに対応し、Search Feedには表示しません。
- **Wire Defense**: Game categoryへNetwork防衛Gameを追加。六角形＋Server風CORE、interceptor missile、1秒reload gauge、Lives別の緑／Orange／赤CORE表示、straight／curve／wave packet route、Best／Max Chainを実装。SoundはOFFで外部通信を追加しません。
- **全RSS新着**: 所有する通常RSSを横断して新着記事をpublication date順にまとめるWidgetを追加。重複source／記事を抑制し、5／10／20／30件を選択出来ます。既存Feed fetch／sanitization経路を再利用します。
- **Release integration**: `APP_VERSION=1.20.0`、`APP_ASSET_REVISION=1.20.0`へ確定し、lazy-loaded Assetも同Revisionへ統一します。

## 互換性

- DB Migration: なし
- SQL実行: 不要
- 新規必須Config / Secret: なし
- 既存`config/local.php`、DB、生成済み`var/`Data: 維持
- Public API Endpoint: `public/api_v1.php`のまま
- 既存API Action名: 維持
- V1.19.0までのWidget／Feed／Stock／Settings／Security boundary: 維持

「全RSS新着」は新規Tableを作らず、既存`dashboard_widget`のSearch Feed設定schemaをprivate marker付きで再利用します。

## Version / Cache

- Application Version: `1.20.0`
- Visible label: `RSS Reader Modernization 1.20.0`
- Asset Revision: `1.20.0`
- Git tag: `v1.20.0`
- Package status: `FINAL`
- Publishable: `yes`

V1.19.0、V1.20-B〜E checkpoint、V1.20.0-RC1の`immutable` Cacheを再利用しないため、正式版ではAsset URLを`1.20.0`へ切り替えます。

## Release validation

V1.20-F RC1ではCurrent Full Regressionに加え、V1.17／V1.17.1／V1.17.2／V1.18、V1.19 Architecture／Security／Cleanup compatibility、V1.20 Game runtime、全RSS新着Validation、PHP／JavaScript／Python syntax、secret scan、Runtime／Complete package manifest／CRC／path safetyを確認しました。本番環境でRC1の主要機能が問題なく動作することを確認した後、V1.20-GでVersion／Asset／Package metadataを正式`1.20.0`へ昇格し、Final GateとPackage verifierを再実行します。

旧`tests/run-v119e.sh`／`tests/run-v119f.sh`および`tests/run-v120f.sh`は各Release Candidate／Historical Release Gateとして保持し、V1.20正式CIではV1.19互換GateとV1.20-G Final Gateを使用します。

## Verification limits

Automated regressionではPHP / Python / NodeによるDomain、HTTP、Security、Frontend contract、Package integrityを確認します。ただし次はHosting／外部Service／Browser環境による差があるため、運用中も必要に応じて確認してください。

- 実HostingのApache / `.htaccess`挙動
- 実外部RSSの応答時間・失敗時挙動と全RSS新着の実際の並び順
- Japanese IMEを使ったRSS Typingの操作感
- Wire DefenseのCanvas操作、Smartphone touch、長時間play時の描画／停止
- PC／Smartphoneでの40px Header、Drawer／Modal、全RSS新着表示
- 実外部Feed / Weather / X / Mail / Camera配信元との接続
- Browser固有のMedia / HLS / CORS挙動
- 長時間Session / Remember Meの実時間経過

## 更新

Version 1.19.0からはCodeの更新のみです。DB Migration、SQL、新規必須設定はありません。`config/local.php`、実DB、生成済み`var/`Dataを維持したまま正式Production ZIPを相対Pathで上書きしてください。詳細は`docs/update.md`と`docs/v1-20-g-production-checklist.md`を参照してください。
