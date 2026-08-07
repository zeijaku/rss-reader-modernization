# V1.7-F 30日間ログインUI／Session統合

## Scope

V1.7-EのRemember Token BackendをLogin画面、Cookie、Session復元、Logout、Password変更へ接続します。通常SessionのIdle 2時間／Absolute 12時間は変更しません。

## Login

Login画面のCheckboxを選択した場合だけRemember Tokenを発行します。未選択でLoginした場合は、そのBrowserに残る既存Remember Tokenを失効・削除します。

Cookie名は`iguguru_remember`です。値は`selector.validator`で、属性はPath `/`、HttpOnly、SameSite=Lax、HTTPS時Secure、有効期限はToken発行時から固定30日です。

## Session restore

Sessionがない、または期限切れの場合にRemember Cookieを検証します。成功時はValidatorをRotateし、Session IDを再生成して認証Sessionを作ります。固定30日期限は延長しません。

不正形式、期限切れ、無効User、不正Validator、DB Errorでは認証を成立させません。Cookieを削除し、通常Loginへ戻します。Token値はLogへ出しません。

## Revocation

- Logout: 現在BrowserのTokenをDBから削除してCookieを削除
- Password変更: Password更新と同じTransactionでUserの全Tokenを削除
- Password変更Response: 現在Browser Cookieも削除

## Deferred

Widget Grid Prototype、Widget Height Migration、端末一覧、個別端末管理、無期限延長はV1.7-Fに含みません。
