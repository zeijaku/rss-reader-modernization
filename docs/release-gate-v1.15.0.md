# Version 1.15.0 Release Gate

- Baseline: GitHub Release `v1.14.1` complete artifact. Release assetのSHA-256をGitHub Release metadataと照合してから使用。
- V1.15-A～Eを累積適用し、Application Versionを`1.15.0`へ確定。
- DB Migration: None.
- PHP 8.4: 既存`tests/run.sh`のRegression範囲をRelease作成環境で分割実行。Playwright Browser testはCI同様にPlaywright unavailableとしてskipし、それ以外のRegressionを完走。
- Version 1.15 focused backend: PASS 32 / FAIL 0.
- Version 1.15 static / contract: PASS 104 / FAIL 0.
- PHP syntax: `app/` / `public/` / `tools/`のPHP 80 filesをPHP 8.4でlint PASS。
- JavaScript syntax: `public/js/utility-widgets.js`をNode.jsでparse PASS。
- PHP 8.1: Release作成環境にBinaryがないため、GitHub Actions matrixをTag前の必須Gateとする。
- Runtime / Complete packageはBuilderとVerifierでSHA-256、Manifest、Path、Secret patternを検証する。
- Production Update ZIPは`app/`と`public/`のみを含める。
- 本番BrowserでEarthquake live data、Sun / Moon、Air Quality、Weather、Smartphone、Solar / Slateを確認後にAnnotated Tag `v1.15.0`を作成する。

## Browser test note

Release作成環境にはPlaywright / Chromiumが存在するため、過去Version向けBrowser testの一部が、現在は削除済みのBootstrap 4 asset参照や環境固有Timeoutへ到達します。少なくともCalendar loading Browser testと旧Game header Browser testについては、同じ正式`v1.14.1` Artifactでも同じ事象を再現し、V1.15変更によるRegressionではないことを切り分けています。

GitHubの通常CIはPlaywrightをinstallせず、PHP 8.1 / 8.4、Python、Node.jsで`tests/run.sh`を実行する構成です。したがって正式Tag前には、mainへ反映したV1.15 treeで両PHP matrixがGREENになることを必須とします。
