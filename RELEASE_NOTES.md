# RSS Reader Modernization 1.50.0-dev.4 — Precision Sniper Dev3 range, drop and wind (Preview)

**この検証用ビルドは正式Releaseではありません。** V1.49.0正式版を置き換える正式リリースではなく、操作感を確認するための開発プレビューです。GitHubのmain、正式タグ、Releaseは変更していません。

- Game WidgetにオリジナルのCanvas狙撃訓練「Precision Sniper」を追加。通常背景＋円形拡大スコープ、手ブレ、Space息止め、固定標的、射撃、R/Zリロード、倍率変更に対応します。Dev1.1で他ゲームと同じBootstrap系の落ち着いた配色と単純な背景表現に変更しました。照準の揺れを中心へ戻る穏やかな往復運動に調整し、弾痕は不規則な貫通穴・紙の欠け・短い着弾エフェクトへ変更しました。空中へのMISSは不自然な弾痕を残しません。
- Dev3：距離別の射撃補正と変動する横風を導入。Stage 1は150/250/350/450m、Stage 2は180/280/390/490m。150m照準合わせを基準に遠方ほど着弾が下へずれ、風下へ左右に流れます。実際の着弾点に対して命中・再命中・地面判定・弾痕を計算し、スコープ中央は狙いのまま維持。標的ラベルと上部の距離・風向/風速・着弾ずれの目安を見ながら上・風上へ補正して狙えるようにしました。一時停止で風と移動標的の時間は停止。ゲーム性重視の簡易近似値であり実物の弾道データではありません。弾速による飛翔時間・移動標的のリードは今後の工程です。
- Dev2調整：命中済み標的にも物理的な当たり判定を行い、追加の弾痕を保持します。標的は命中後も残し、初回命中だけスコア加算。2発目以降も照準円の中心・中間・外周という領域と標的中心からの相対位置（横／縦）を表示し、再命中は加点なしと明示。移動標的の弾痕は標的と同期します。標的の配色は強い赤から落ち着いた赤茶・生成り・グレーに変更しました。既存のゲーム・保存仕様・音声ON/OFFは変更しません。
- Dev2でStage 1の固定4標的とStage 2の移動標的（1枚固定＋3枚往復）を追加。ステージ変更は次のStart/Restartで反映し、Pause中は標的も停止。表示・着弾判定と弾痕は同一のゲーム内時刻に同期します。音は初期OFF、明示的なON操作後だけWebAudioで低域寄りのこもった射撃音を短く合成し、OFFでミュート、Destroyでリソースを閉じます。外部音源の取得はありません。弾速/飛翔時間と移動標的のリード、4難易度、能力強化、進捗保存および本格的なリロード演出は今後のDev工程の対象です。
- 共通Game WidgetのStart / Restart / Pause / 拡大 / Destroy、保存スコープと現行セキュリティ境界を利用します。DB Migrationはありません。
- PHP 8.1 / 8.4 CIおよびSniper Focused Testを実行。実端末での照準操作感とPC/スマホ表示はユーザー確認前です。
- 検証用Runtime ZIPは既存パッケージ生成器で作り、Mail用のComposer IMAP依存ファイルを含めます。設定・既存ユーザーデータを上書きしないよう、検証環境で使用してください。

---

## Previous formal release notes (V1.49.0)

# RSS Reader Modernization 1.49.0

V1.49.0は、既存のDashboardとArticle Actionsの動作を維持しながら、Feed表示・通信・Reader Modeと記事操作を分割して保守性を改善するModernization Releaseです。Application VersionとAsset Revisionは`1.49.0`。Database Migrationと新規必須Config変更はありません。

## Changes

- DashboardのFeed描画・取得・見出し、Search Feed、Widget順序、タブスワイプを独立したモジュールへ整理。従来のイベントと共通API／CSRF処理を保持します。
- Article Actionsをメニュー配置・開閉／キーボード操作・URLコピー／X共有・Stock保存／解除・Task追加・Reader Modeに分離。現在の操作対象記事の状態は1か所で管理します。
- Reader ModeのFeed共通関数参照を明確化し、RSS本文と全文取得が読み込み中のまま停止するスコープ参照不具合を修正。失敗時はRSS本文を維持し、外部URLの安全性検証と安全化済みHTMLのみによる全文表示を保持します。
- RSS取得のサイズ上限は変更しません。既定2MiBを超えるFeedは意図されたHTTP 422と画面内エラー表示、再試行で扱います。
- 新しいブラウザモジュールの読込順・実行型回帰テストを強化し、既存のMail・Calendar・Games・Remote Files機能を継続利用できる構成にします。
- MailのComposer依存ライブラリ（ImapEngine 1.25.6と必要な依存）を正式Runtimeパッケージで検証します。

## Verification limits

- Dev10-A〜DはユーザーによるPC中心の実画面確認済み。Reader Mode、Stock、Task、コピー・X投稿に問題報告はありません。ただし全OS／ブラウザ／エラー経路の網羅確認を意味しません。
- GitHub ActionsでPHP 8.1／8.4回帰テスト、Apache隔離、Node実行型Reader・Article Actions回帰を確認します。Python版Playwrightのブラウザテストは未導入のCIではスキップされます。
- CNET Japan RSS（計測時約2.98MiB）は2MiB上限を超えます。この仕様上の取得拒否は不具合とは判定せず、既存のセキュリティ上限は維持します。
- インターネット上のRSS形式や記事抽出結果、すべてのIMAP環境での動作を保証するものではありません。

## Deployment and rollback

[正式版の更新・確認・復旧手順](docs/v1.49.0-release.md)を参照してください。コード・隠しファイル・設定・DB・稼働データをバックアップし、正式Runtime ZIPをアプリケーションルートへ配置してください。 `vendor/autoload.php` を含むMail依存ライブラリとJavaScriptファイルが揃っていることを確認します。DB Migrationはありません。不具合時はコードを旧版へ戻し、既存の秘密設定と保存データを保持してください。
