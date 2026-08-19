# V1.17.2-A ユーザー確認Checklist

## 事前準備

- [ ] Version 1.17.1環境へ差分ZIPを上書きした。
- [ ] `config/local.php`等のprivate設定へ`APP_X_BEARER_TOKEN`を追加した。
- [ ] Bearer TokenをGit／公開Directory／Browser codeへ置いていない。
- [ ] X Developer ConsoleでAppとAPI利用状態を確認した。
- [ ] Browserを強制再読込した。

Bearer Token／Creditがまだない場合、先にUI追加だけ確認し、実投稿取得はV1.17.2-Bで行っても構いません。その場合はWidget内に「Bearer Tokenが設定されていません」というServer側Errorが表示されることを確認してください。

## 基本表示

- [ ] Drawer → Widget追加 → RSS に`X Timeline`が表示される。
- [ ] PublicなX usernameを入力してWidgetを追加出来る。
- [ ] `@username`形式と`username`形式のどちらでも登録出来る。
- [ ] Account名／@username／投稿本文／日時が表示される。
- [ ] `Xで開く`で対象Postを別Tabで開ける。
- [ ] 3件設定なら最大3件、5件なら最大5件、10件なら最大10件が表示される。
- [ ] 返信／リポストのON/OFFが設定どおり反映される。

## 更新／設定変更

- [ ] Headerの更新Buttonで再取得出来る。
- [ ] Title／色／Width／Height変更後、ページ全体ReloadせずX Cardだけ更新される。
- [ ] username変更後、対象X Cardだけが新Accountへ切り替わる。
- [ ] X Widgetを削除してもページ全体Reloadしない。

## V1.17.1回帰確認 — 重要

- [ ] YouTube Liveを再生したままX Widgetの設定を変更しても、YouTube再生が止まらない。
- [ ] Clock Timerを動かしたままX Widgetの設定を変更しても、Timer状態が不要に失われない。
- [ ] Weather等の既存Widget設定更新が従来どおり対象Card更新のまま動く。

## Security／Network確認

Browser Developer Toolsで確認します。

- [ ] BrowserからのX Widget request先は`api_v1.php`で、`api.x.com`へBrowserが直接requestしていない。
- [ ] HTML／JavaScript／Network request body／responseに実Bearer Tokenが出ていない。
- [ ] ConsoleにJavaScript Errorが出ていない。
- [ ] 非公開Accountを指定した場合、App-onlyでは表示出来ない旨のErrorになる。

## Cache／API利用量

- [ ] 通常再表示では短時間に毎回X APIを叩かずCacheが使われる。
- [ ] Headerの手動更新は強制取得であることを理解したうえで確認する。

## 異常系（可能な範囲）

- [ ] Token未設定時にApplication全体が落ちず、X CardだけError表示になる。
- [ ] 無効Token時に認証ErrorがX Cardだけに表示される。
- [ ] Rate Limit／Usage上限時にX CardだけErrorまたはTransient時のstale cache表示になり、他Widgetは動作する。
