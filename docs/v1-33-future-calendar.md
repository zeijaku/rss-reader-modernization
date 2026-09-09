# V1.33後のCalendar改善候補

V1.33-Hでは現行Calendar EnhancementをRelease候補として固定し、次の2機能はV1.33対象外として実装せず、次期以降の正式候補として記録します。

## 1. 日程のコピー

推奨する最小構成は、予定詳細の「コピー」操作から新規作成Modalを開き、元予定のTitle、Note、色、終日／時刻、URL、期間を初期値として引き継ぐ方式です。保存前に日付・内容を編集でき、元予定は変更しません。

繰り返し予定では、操作対象を次のように明示します。

- この予定のみをコピー: 選択したOccurrenceを通常予定として複製する。
- シリーズをコピー: 繰り返しRuleと期間を含む新しいシリーズを作る。

初期実装では「この予定のみ」を既定にし、無確認でシリーズ全体を複製しません。V1.33のOccurrence identityと共通range helperを再利用し、Server側でOwner Scope、CSRF、入力Validation、重複送信防止を行います。

## 2. 日程のDrag & Drop

想定する操作は次のとおりです。

- 月表示: 予定を別の日へ移動し、複数日予定は期間の長さを維持する。
- 週／日表示: 時刻指定予定を別の日・時刻へ移動し、所要時間を維持する。
- 終日領域: 終日／複数日予定の日付を移動する。

繰り返しOccurrenceを移動する場合は、Drop後に「この予定のみ」または「シリーズ全体」を確認します。「これ以降」はV1.33と同様、独立した将来検討とします。

実装条件:

- HTML5 Drag & Dropだけに依存せず、Touch端末用の移動操作とKeyboard操作を用意する。
- Drop中は候補日／時刻を表示し、保存失敗時は元位置へ戻す。
- Client表示だけを先に確定せず、Server応答後のrevisionを正として再描画する。
- 同時編集には既存の楽観的revisionを利用し、古い状態による上書きを拒否する。
- Owner Scope、CSRF、XSS escaping、PDO、URL／日付／時刻Validationを維持する。
- 月末、年末、週境界、DSTを含むTimezone、複数日、Occurrence overrideを自動Testへ含める。

## 推奨Phase

1. Copyの通常予定／Occurrence単体を追加する。
2. Copyのシリーズ対応を追加する。
3. 月表示Drag & DropをPCとKeyboard fallback付きで追加する。
4. 日／週の時刻Drag & DropとTouch UIを追加する。
5. Calendar全体の回帰、Accessibility、Smartphone、Security、Migration不要性を確認する。

DB変更は現時点では不要と見込まれます。Copyは既存event／recurrence構造、Drag & Dropは既存event更新またはV1.33 exception overrideで表現できます。ただし正式着手時にAPI contractと監査要件を再評価します。
