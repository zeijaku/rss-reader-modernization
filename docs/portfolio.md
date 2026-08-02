# Portfolio掲載用メモ

## 短い説明

約10年前に作成したPHP製RSS Readerを、既存機能とData構造を確認しながら、Security、PHP 8、MySQL 8、RSS Engine、Frontend、Test、運用資料の順に段階的にModernizationしたProjectです。

## 少し長い説明

元のApplicationを一度に作り直すのではなく、Legacy版を比較資料として凍結し、Authentication、Session、CSRF、SSRF、XSS、PDO、Validationを先にSecure Baselineとして整理しました。その後、RSS / Atom取得処理の責務分離、Cache、ETag / Last-Modified、Retry、stale-if-error、FrontendのResponsive / Accessibility、Dependency、License、設置・復旧資料まで段階的に進めています。

既存の4タブ、Feed CRUD、Stock、Settings、公開API、DB契約を維持し、各工程でRegression testを追加しています。

## 技術要点

- PHP 8.1+ / MySQL 8系
- PDO parameter binding
- password_hash / password_verify
- Session、CSRF、owner scope
- SSRF-safe HTTP fetch
- RSS 2.0 / RSS 1.0 / Atom Adapter
- Server-side cache、ETag、Last-Modified、HTTP 304
- Retry-After、Backoff、stale-if-error
- Responsive、Keyboard、Focus、ARIA
- PHP / Python / Node.jsによるRegression test
- GitHub Actions CI
- Backup、Restore、Rollback資料

## 改修の見せ方

Portfolioでは「古いCodeを新しくした」だけでなく、次を示します。

1. Legacyの仕様とRiskを先に確認した。
2. SecurityとData保全をFrontendより先に行った。
3. 一度に全面Rewriteせず、小さな工程とCommitへ分けた。
4. 既存契約をTestで固定してから内部を整理した。
5. Release時のLicense、設置、Backup、復旧まで含めた。

## Screenshot候補

- 4タブを表示したDashboard
- FeedのLoading / Empty / Error
- Mobile 1列、Tablet 2列、Desktop 4列
- DrawerとSettings
- GitHub Actionsの成功画面
- RoadmapまたはSecurity / Engine構成図

Screenshotには実Feed URL、利用者名、Mail address、Stock URL、Cookie、Server情報を含めません。Demo用の架空Dataを使用します。

## AI支援の説明例

```text
Legacy codeの整理、確認項目の洗い出し、Test追加、Documentation作成にAI支援を使用しています。改修方針、採用判断、動作確認、Git履歴、公開範囲の判断はProject ownerが行っています。
```

AI利用を隠す必要はありませんが、AIが自動的に完成させたような表現にはせず、どこへ利用し、何を自分で判断・確認したかを分けます。

## 過度に主張しないこと

- 大規模Trafficでの性能を実証したとは書かない。
- 全Feed形式を完全に処理できるとは書かない。
- Production E2Eが未確認の段階で完全対応と書かない。
- Bootstrap 5等へ全面刷新したとは書かない。
- AI未使用とは書かない。

正式なVersion 1.0.0、Release URL、最終Test値はM4-G完了後に追記します。
