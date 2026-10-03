# RSS Reader Modernization 1.43.0

V1.43.0正式版は、Cursor Fieldだけを対象にしたFeature Releaseです。Application Version / Asset Revision は `1.43.0`。V1.42.0からDB Migration、config変更、認証/API変更、他Game / Calendar変更はありません。

## Cursor Field

- 従来の14×9固定Gridへ戻る多数の□を廃止し、初期3個の○/□がWidget内を自由に漂う方式へ変更しました。
- ○/□はWidget内壁で反射し、物体同士でも衝突・反射します。□は回転せず向き固定です。
- Mouse / Pen Cursorを小さな円形Colliderとして扱い、接触時に反射します。Cursorを速く動かすと、その速度の一部をBodyへ伝えます。
- 空いている場所をClickすると、その位置へ○/□を交互に1個追加します。追加直後は速度0で、他BodyまたはCursorとの衝突から動き始めます。
- 物体上Clickでは追加しません。最大24個です。
- Widget ResizeではBodyを再生成せず、現在位置と速度を新しいCanvas寸法へ比例変換します。
- dev.2で通常移動とCursor由来の速度を約30%抑え、青灰・セージ・くすみ紫・トープ系の落ち着いた配色へ調整しました。

## Lifecycle / compatibility

- requestAnimationFrameを使用し、WidgetがViewport外またはPage hidden時はAnimationを停止します。
- `prefers-reduced-motion: reduce` では初期自律速度を0にし、User操作でのみ動き始めます。
- Score / Clear / Game Over / browser save / network通信は追加していません。Reload時は初期状態へ戻ります。
- V1.42.0から追加SQLなしで更新できます。既存設定・DB・private runtime dataを維持してください。

## Verification limits

- GitHub ActionsのCurrent gateをPHP 8.1 / PHP 8.4で実行します。
- Cursor Field純粋物理テストでwall / body-body / mixed-shape / pointer / spawn挙動を検証します。
- Browser fixtureはDesktop / 360pxで初期Body、空白Click追加、物体上Click、Pointer干渉、Resize維持、Page overflowを確認できるよう追加しています。Current CIでは既存方針に合わせ構文確認を行い、Playwright実行環境がある場合にFocused testとして実行します。
- 実際の速度感・配色・基本操作はユーザー確認済みです。

## Release

Tag: `v1.43.0`

Runtime package: `rss-reader-modernization-1.43.0.zip`

Complete Source package: `rss-reader-modernization-1.43.0-complete.zip`
