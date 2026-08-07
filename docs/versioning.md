# Visible Version Marker

Application Versionと画面表示Labelは`app/version.php`で管理します。

```php
const APP_VERSION = '1.8.0';
const APP_VERSION_LABEL = 'RSS Reader Modernization 1.8.0';
```

- Stable release: `RSS Reader Modernization 1.8.0`
- Git Tag: `v1.8.0`
- Release commit: Version 1.8正式化の最終Commit

Version 1.8開発中は`1.8.0-dev.N`を使用しました。正式Releaseでは開発中表記を残さず、Application Version、Label、Runtime ZIP、Complete ZIP、Release Notes、Tagを同じ`1.8.0`へ揃えます。

過去のSB、M1、M2、M4、V1.1～V1.7、V1.8-A～E表記は履歴Document内に残しますが、現在Versionを示す入口には使用しません。

Version 1.8はGitHub `main`のVersion 1.7.0をBaselineとして開発しました。Complete Sourceを`feature/v1.8-stock`へRelease Commitとして反映し、Fast-forward可能な場合だけ`main`へ統合します。

TagはSource反映前に作成しません。`main`がVersion 1.8.0 Release Commitを指し、Working Treeと本番確認が完了した後にAnnotated Tag `v1.8.0`を作成します。既存Tagの移動・削除やForce pushは行いません。
