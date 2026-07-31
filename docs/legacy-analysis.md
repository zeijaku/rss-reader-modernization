# Legacy Analysis

## 目的

この文書はModernization Project開始時に解析したLegacy RSS Readerの構造と、Secure Baselineで解消対象になった問題を公開可能な粒度でまとめたものです。

解析元は凍結済みのLegacy source、Legacy API補完ファイル、Legacy MySQL dumpです。Legacy自体は修正せず、Modernized treeとは分離しています。

## Legacy architecture

Legacy版は主に次の構成でした。

- `index.php`: Session、Login/Register、Dashboard描画、AJAX client
- `api_v1.php`: Feed登録・変更・削除、Stock、Settings、Tab、外部Feed取得
- `common/common_db.php`: PDO接続とCRUD
- `common/common_func.php`: cURL、RSS/Atom parser、Hash、logging
- `common/common_login.php`: Login/Register UI
- `logout.php`: Session破棄
- `dat/`: application/access log
- `session_file/`: Web root配下のSession保存先

標準DBはMySQLで、SQLite分岐は残存コードとして存在していました。

## Legacy database

4テーブルを使用していました。

```text
ig_user_info
ig_user_conf
ig_content
ig_content_stock
```

役割:

- `user_info`: account / credential
- `user_conf`: theme、Navbarリンク、4タブ名
- `content`: Feed URL、owner、location、style、logical delete
- `content_stock`: Stock URL、title、owner、logical delete

Legacy dumpには実運用由来のcredential/operational dataが含まれていたため、GitHub対象にはしません。

## Legacy functions confirmed

- Register / Login / Logout
- userごとのFeed URL登録
- 4タブFeed配置
- Feed card style
- Feed URL変更 / logical delete
- ページ表示時の外部Feed取得
- 最大5件程度の記事表示
- 記事URL Stock
- Stock一覧
- Bootstrap theme変更
- Navbarリンク4件
- Tab名4件

Feed item自体はDB保存しておらず、cache / ETag / Last-Modified管理もありませんでした。

## High-risk findings

解析で特に優先した問題は次の通りです。

### Authentication / authorization

- API boundaryに十分な認証強制がなかった。
- owner/user targetingをPOST値から受け取る経路があった。
- Content update/deleteにowner条件が不足していた。
- Settings / Tab updateのtarget userをrequestで指定できる設計だった。

### CSRF

- 状態変更APIへSessionに結びついたCSRF保護がなかった。
- 一部は固定文字列tokenだった。

### SSRF / TLS

- 任意URLをserver-side cURLへ渡す経路があった。
- automatic redirectを許可していた。
- TLS peer / hostname verificationが無効だった。
- Response size / HTTP status handlingが十分でなかった。

### XSS

- Feed由来text/linkをHTML文字列として組み立てる経路があった。
- DB/Session由来設定値の出力escapeが一貫していなかった。

### SQL / error handling

- 一部SQLが文字列連結だった。
- DB例外詳細をpublic responseへ出す経路があった。
- user + user_confの作成がatomicではなかった。

### Session / password / secrets

- Session保存領域がWeb root配下だった。
- Login成功時のSession ID再生成が無効化されていた。
- 長期間Cookie、cookie security attributes不足。
- Legacy password schemeは現在の `password_hash()` 方式ではなかった。
- Secret configurationがapplication tree内に存在した。
- Legacy ZIPにDB dump、logs、session関連ファイルが含まれていた。

## Functional/runtime findings

代表例:

- 日時formatで分と月を取り違える `H:m:s`。
- 4タブのlocation mappingが0/2/3/3となる経路。
- Feed type分岐のassignment bug。
- Fetch失敗をText成功として扱う可能性。
- Item数を常に5件前提としたclient loop。
- 4件単位でないcard rowのHTML構造。
- Settings/Tab保存の不整合や二重submit。
- Atom linkの選択不足。
- PHP 8でWarning/TypeError等へ発展する曖昧な値処理。

## Legacy data findings and current policy

Legacy dumpではcredential形式の混在、duplicate identity、`content_owner=0` 等のLegacy/test dataが確認されました。

Secure Baselineでは次の方針を採用しています。

- 不明なLegacy credential形式を推測しない。
- Existing-user credential compatibilityを要件にしない。
- Duplicate identityは認証時fail closed。
- Legacy duplicate/orphanを自動削除・統合しない。
- 新規環境ではMySQL 8の空DB + sanitized `schema.sql` を推奨。
- Production dumpをrepositoryへ入れない。

このため、Legacy data cleanupとSecure Baselineの安全化を分離できています。

## Deferred findings

Secure Baseline後へ意図的に残した代表項目:

- Server-side Feed cache
- ETag / Last-Modified
- Feed item identity / persistence
- Feed status / error count
- Bootstrap / jQuery / Drawer等のFrontend dependency刷新
- assets整理
- UI/UX / accessibility改善
- Foreign Key policy

これらは [`roadmap.md`](roadmap.md) で扱います。
