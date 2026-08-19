# V1.17.2-B User Checklist

V1.17.2-BはRelease Gate段階のため、全項目を毎回の小修正で繰り返すのではなく、最終候補へ一度適用したあとに確認してください。

## 1. 更新前

- [ ] V1.17.1のApplication codeをBackupした。
- [ ] `config/local.php`をBackupした。
- [ ] DatabaseをBackupした。
- [ ] X Timelineを使う場合、実Bearer TokenをGit／Chat／Screenshotへ貼っていない。

## 2. 配置

- [ ] `rss-reader-modernization-1.17.2.zip`をApplication rootへ展開した。
- [ ] `config/local.php`、実DB、`var/`をRelease ZIPで置換していない。
- [ ] DB Migrationを実行していない（V1.17.2では不要）。
- [ ] X Timelineを使う場合、Server固有`APP_X_BEARER_TOKEN`を保持している。
- [ ] `var/cache/x/`をPHPが書込み可能。

## 3. Version / Browser

- [ ] Browserを強制再読込した。
- [ ] Version表示が`RSS Reader Modernization 1.17.2`。
- [ ] Consoleに今回追加部分由来のJavaScript Errorがない。

## 4. X Timeline / 上級者向け案内

- [ ] Widget追加CatalogからX Timelineを開くと「上級者向け機能」が目立って表示される。
- [ ] X Developer Platform、Pay Per Use、Bearer Token、Server側`APP_X_BEARER_TOKEN`が必要と案内される。

### Token未設定を確認する場合

実運用Tokenを消さず、Test環境等で行ってください。

- [ ] `APP_X_BEARER_TOKEN`未設定では赤い警告が出る。
- [ ] 未設定ではX Timelineの追加Buttonが無効。
- [ ] Server側create APIも未設定を拒否する。

### 正常設定

- [ ] Token設定直後、まだ実X API通信していない状態は「接続未確認」と表示出来る。
- [ ] 公開usernameを指定し、投稿を取得出来る。
- [ ] 実取得成功後は「X API接続を確認済み」と表示される。
- [ ] 3／5／10件の表示件数が切替出来る。
- [ ] Reply／Repostの含有設定を変更出来る。
- [ ] 手動更新出来る。

### 認証失敗を確認する場合

実Tokenを不用意に壊さず、Test用設定で確認してください。

- [ ] HTTP 401を受けた現在Tokenは「X API認証に失敗」と案内される。
- [ ] Raw Bearer Token自体は画面へ表示されない。

## 5. no-reload / 他Widget回帰

- [ ] X TimelineのTitle／色／件数／username等を変更してもページ全体Reloadにならない。
- [ ] X Timeline削除で不要な全画面Reloadが発生しない。
- [ ] YouTube再生中にX設定を変更しても、無関係なYouTubeが不要に停止しない。
- [ ] Clock Timer動作中にX設定を変更しても、無関係なTimer stateを不要に失わない。

## 6. Security / Network

- [ ] Browser NetworkはRSS Readerの`api_v1.php`へ接続し、Browserから`api.x.com`へ直接接続しない。
- [ ] HTML／JavaScript／RSS Reader API responseへBearer Tokenが出ていない。
- [ ] `config/local.php`や実TokenがRelease ZIPへ含まれていない。
- [ ] `var/cache/x/`のRuntime CacheがRelease ZIPへ含まれていない。

## 7. Release判断

- [ ] 既存RSS、Stock、Task、Calendar、Mail、Camera / Video等に大きな回帰がない。
- [ ] X本体の「おすすめ / For You」はV1.17.2の対象外であることを理解している。
- [ ] 上記が問題なければV1.17.2 Release候補としてGitHub PR／CI確認へ進める。
