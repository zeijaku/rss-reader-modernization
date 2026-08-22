# V1.19-D Cleanup / Documentation

V1.19-DはB/Cで変更したRuntime boundaryを整理し、V1.19-E Release Candidate前にSourceとDocumentationの認識差を減らすPhaseです。

## Runtime cleanup

Account password change formへ、Browser password managerが期待する`autocomplete="username"`補助Fieldを追加しました。Secure BaselineではRaw login emailを保存しないため、補助FieldへIdentityを新規保存・出力せず空値のまま非表示とします。Account password update API payloadは従来どおり`current_password` / `new_password` / confirmationだけです。

対象ViewはDashboard modal、Stock、Settingsの3か所です。

## Documentation

- `v1-19-architecture.md` — Bで分割したAPI / Dashboard境界
- `v1-19-public-endpoints.md` / CSV — 7 Public PHP Endpoint Matrix
- `v1-19-security-boundary.md` — Deployment / API / Headers / Runtime boundary
- `v1-19-security-checklist.md` — 今後の機能追加時Checklist
- `configuration.md` — Registration throttle / API request limit
- `deployment-checklist.md` — `public/`推奨とRental Server互換構成を区別
- `security.md` / root `SECURITY.md` — V1.19-Cと現在のRelease運用へ整合

## Release material cleanup decision

Rootに残る過去`APPLY_NOTE_*` / `CHECKLIST_*` / `UPDATED_FILES_*` / `TEST_REPORT_*`をV1.19-Dで一括移動することは**見送ります**。過去Builder、Test、GitHub linkがPathを参照している可能性があり、Runtime効果がない整理のためにRelease Candidate直前の参照切れRiskを増やさない判断です。

V1.19-Dの新規恒久Documentationは`docs/`配下へ置き、過去資料の物理移動は必要性が出たときに専用Phaseで参照検索を行ってから実施します。

## Compatibility

- DB migration: なし
- SQL: 不要
- 新規必須config/secret: なし
- API action/response: 変更なし
- APP_VERSION: 1.18.0のまま
- APP_ASSET_REVISION: 1.18.0-r4のまま
- GitHub write: なし
