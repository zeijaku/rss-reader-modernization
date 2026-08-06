# RSS Reader Modernization 1.5.0 Release Notes

Release date: 2026-08-06

Version 1.5.0は、既存Clock Widgetへ小型のCountdown Timerを追加するReleaseです。Timerは別Widget種類を増やさず、Clockの配置、見出し、横幅、Theme、並べ替え、削除をそのまま利用します。

## Clock Timer

- Clock Widget内の「時計／タイマー」切替
- 1／3／5／10／25分Preset
- 1～1440分の任意時間
- 開始、一時停止、再開、Reset
- `HH:MM:SS`形式の残り時間
- 終了時の短い「終了」表示、背景・枠の視覚強調
- 音、Browser通知、Vibrationなし

## 時間補正と復元

Timerは毎秒の単純減算だけに依存せず、開始・再開時の終了予定時刻`endAt`から残り時間を再計算します。Reload、`focus`、`pageshow`、`visibilitychange`、Sleep／Background復帰後も現在時刻へ補正します。

状態は次の順で保存・復元します。

```text
localStorage → sessionStorage → Memory
```

正常Copyが複数ある場合は`savedAt`が新しいものを採用し、壊れたCopyだけを除去します。すべて異常な場合は安全な初期状態へ戻します。

## 複数Tabと操作性

- 同じUser／Widgetの`localStorage`更新をBrowser Tab間で同期
- 別User／別Widgetの状態を分離
- 同一操作の短時間連打とKey Repeatを抑止
- 44px以上の操作領域
- Keyboard、ARIA Live Region、Focus表示
- `prefers-reduced-motion`と全8 Themeへ対応

## Smartphone調整

V1.5-C / R2～R4では、実機確認で判明した次の点を修正しました。

- Feed Table／HeaderがSmartphone幅を押し広げる横Overflow
- 長いFeed名と右端操作領域の収まり
- RSS概要［＋］／［－］IconのSmartphone表示
- `dashboard.js`だけ古いCacheが残った場合の読込みURL切替

Asset Cache Bustingの一元化は今後の保守課題とし、Version 1.5.0では確認済みの個別Queryを維持します。

## DB／設定

Version 1.5による新しいDB構造はありません。

- Table／Column追加：なし
- Migration／SQL：なし
- 必須設定追加：なし
- `config/local.php`変更：なし
- 外部Library／Framework追加：なし
- Timer Server保存：なし

Clock／Timer Widgetの登録は既存`dashboard_widget` Tableを利用し、実行状態はBrowser Storageだけへ保存します。

## Update

Version 1.4.0からVersion 1.5.0への更新は、Codeを更新してBrowserを再読み込みします。SQLやMigrationは実行しません。`config/local.php`、Server固有`.htaccess`、実DB、`var/`の生成Dataは不用意に上書きしないでください。

## Artifacts

- `rss-reader-modernization-1.5.0-complete.zip` — Source、Tests、Documentation、GitHub metadataを含む完全統合ZIP。
- `rss-reader-modernization-1.5.0.zip` — Server配置用Runtime ZIP。TestsとGitHub metadataを除外。
- 各ZIPの`.zip.sha256`
- ZIP内部のFile単位Manifest

## Verification limits

自動TestではPHP／JavaScript／Python／Shell構文、Security境界、Authentication、Session、CSRF、RSS／Atom、Cache、Widget CRUD、Search Feed、記事Actions、既存Widget、Icon Quest、Clock Timer、Browser Storage、複数Tab、Keyboard、Touch、Responsive、Accessibility、全8 Theme、Schema、Secret Pattern、ZIP CRC／Path、Manifest、Documentation Link、Version表記を確認します。

この実行環境に実MySQL Serverまたは利用可能な`pdo_mysql`接続先がない場合、実DB接続、Hosting固有設定、実Feed到達性、実Mail配送、BackupからのRestoreは利用者環境での最終確認が必要です。iPhone SafariでのSmartphone表示とTimer終了表示は利用者環境で動作確認済みですが、端末・Browser固有のStorage制限は各環境で確認してください。

## License

Project本体は`LICENSE`、外部Assetは`THIRD_PARTY_NOTICES.md`と`licenses/`を参照してください。
