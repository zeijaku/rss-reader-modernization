# V1.18.0 final pre-Git apply note

Git登録前の最終確定版です。

- Application Version: `1.18.0`
- Release tag target: `v1.18.0`
- Asset Revision: `1.18.0-r2`
- DB Migration / SQL: なし
- 新規必須config / Secret: なし

最終実機確認で修正した内容:
1. 長時間放置後、Remember MeによるSession復旧時に旧CSRF Tokenを保持したPageが403になるCaseを短時間Grace＋Response Header同期で解消。
2. Smartphone Calendarの固定minimum widthを解除し、7列GridをCard幅内へ収める。
3. 旧`immutable` Cacheを確実に回避するためAsset Revisionを`1.18.0-r2`へ変更し、Calendar動的Asset loader／Camera streaming fallbackも同Revisionへ統一。

Runtime ZIPは相対PathでCodeを更新し、`config/local.php`、実DB、`var/`生成Dataは上書きしないでください。
