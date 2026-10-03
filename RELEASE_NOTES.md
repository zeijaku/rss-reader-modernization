# RSS Reader Modernization 1.42.0

V1.42.0正式版は、V1.41.0本番確認後に行ったCalendar期日強調の再明滅修正と、Tower Defenseの戦略性拡張をまとめたFeature Releaseです。Application Version / Asset Revision は `1.42.0`。V1.41.0からのDB Migration、config変更、認証/API変更はありません。

## Calendar

- 「期日を強調する」が有効な予定は、従来の期日判定と赤い上・右・下の強調を維持します。
- 前日〜期日の緊急状態では表示時のパルスに加え、ページが表示されている間だけ1分ごとに同じパルスを再実行します。
- 1分間隔はClient側Schedulerのみで、1分ごとのCalendar API通信は追加しません。既存の表示中3分間隔更新・手動更新はそのままです。
- 非表示Tabの時間は再明滅Cadenceから除外し、`prefers-reduced-motion` ではAnimationを行わず静的強調だけを残します。

## Tower Defense

- Stage 1〜3は従来の12×8・単一路線を維持し、Stage 4〜6を24×8・2入口2経路の広域Mapへ拡張しました。後半StageはEasyを含む全Difficulty・全Waveで両入口を使い、Wave 4 / 8は両RouteからBossが進入します。
- Map / Route / RockはStage固定です。DifficultyやCard幅、拡大/縮小、Resizeで経路や配置は変化しません。
- Easy / Normal / Hard / Nightmareを追加しました。難易度は開始前に選択し、開始資金・Base HP・敵数/構成/HP/速度/Reward/Spawn間隔を変えます。Map形状は共通です。
- Browser save schemaを2へ更新し、Stage・Difficulty・準備CheckpointとStage×Difficultyの★を保存します。V1.41の旧★はNormalへ移行し、有効な旧CheckpointもNormalとして復元します。
- 旧V1.41 `.state` keyは上書きせず保持し、新しい進行は `.state.v2` へ保存します。新しいRoadと衝突する旧Towerだけを最寄りの配置可能Cellへ決定的に移動します。
- 広域盤面は約1080×360pxを維持します。2列Card・狭いCard・Smartphoneでは盤面内だけを横Scrollし、十分な幅の3列CardとDesktop拡大表示では全体を表示します。

## Upgrade and compatibility

V1.41.0からは追加SQLなしで更新できます。Application、`config/local.php`、Database、private runtime dataをバックアップしたうえで正式Runtime ZIPを展開してください。既存設定とDBを維持し、既存DBへ `database/schema.sql` を実行しません。

旧TD Browser保存は自動移行対象ですが、元のschema-1 keyを保持するためCode rollback時にはV1.41系が旧Saveを再読込できます。Widget自体を明示的に削除した場合のみ旧/new両TD stateを削除します。

正式Assetは共通Release WorkflowでRuntime / Complete Source ZIP、SHA-256、secret scan、clean-room checks、GitHub Artifact Attestationを検証して公開します。ProductionへのCode反映はGitHub Release公開とは別工程です。

## Verification limits

- GitHub ActionsのCurrent gateをPHP 8.1 / PHP 8.4で実行します。
- Calendar再明滅はDesktop/360px fixtureで1分Cadence、DOM置換継続、hidden/resume、reduced-motion、不要なAPI requestが増えないことを検証しています。
- TDはCore/Browser gateで6 Stage、4 Difficulty、後半2 Route、Save schema 2、旧Save移行、2列内部Scroll、3列全体表示、拡大時全体表示、Page overflowなしを検証します。
- 実際のStage 4〜6の操作感・難易度・2列/3列表示はProduction相当環境でユーザー確認済みです。物理端末固有のBrowser挙動や将来のBalance調整余地は自動検証の対象外です。

## Release

Tag: `v1.42.0`

Runtime package: `rss-reader-modernization-1.42.0.zip`

Complete Source package: `rss-reader-modernization-1.42.0-complete.zip`
