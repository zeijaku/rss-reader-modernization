# V1.7-E Remember Token DB／Backend

## Scope

V1.7-EはRemember Tokenの保存とDomain処理だけを実装します。Login画面、Cookie操作、Session切れ後の自動Login、Logout／Password変更との統合はV1.7-Fへ分離します。

## Token形式

```text
selector.validator
```

- Selector: 12 random bytesをHex化した24文字
- Validator: 32 random bytesをHex化した64文字
- DB保存: ValidatorのSHA-256 Hashだけ
- 有効期限: 発行時点から固定30日

## Backend処理

`app/remember_token.php`に次を追加しました。

- Active UserへのToken発行
- 厳密なToken形式Validation
- Selector完全一致検索
- Validator Hashの`hash_equals()`比較
- 成功時のValidator Rotation
- 発行時の有効期限維持
- Expired／Inactive User／不正ValidatorのFail Closed削除
- 現在端末相当のToken失効
- User全Token失効
- Expired Token Cleanup

MySQLではValidationとRotationをTransaction＋`FOR UPDATE`で行います。更新時は以前のHashもWHERE条件へ含め、競合時は自動Loginを成立させません。

## Security

- Raw ValidatorやCookie値をDBへ保存しない
- Token値をLog／Debug出力しない
- 不正Validator時は同じSelectorを削除
- 無効UserのTokenは削除
- Validator Rotationで古いCookieの再利用を拒否
- DB Error時は例外を上位へ返し、認証成功として扱わない

## Deferred to V1.7-F

- Remember Cookie名と属性
- Login画面Checkbox
- Login成功時のCookie発行
- Session切れ後のToken検証
- 自動Login後のSession ID再生成
- Logout時の現在Token失効
- Password変更時のUser全Token失効
