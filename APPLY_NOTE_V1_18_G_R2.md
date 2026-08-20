# V1.18.0 pre-Git R2 apply note

V1.18.0 Release Gate後の実機確認で見つかった2点を、Git登録前に修正した候補です。

1. 長時間放置後のSession自動復旧時に、開いたままのPageが旧CSRF Tokenを送って403になるCase。
2. Smartphone Calendarが500pxのminimum widthを持ち、Card幅を超えてPage横幅へ影響するCase。

DB Table / Column / Migration / SQL / config追加はありません。
APP_VERSION / APP_VERSION_LABEL は 1.18.0 のままです。実機確認で旧immutable Cacheが残ることを確認したため、最終APP_ASSET_REVISIONは1.18.0-r2へ更新します。

本番確認ではRuntime ZIPを相対Pathで上書きし、config/local.php、DB、var生成Dataは上書きしないでください。
最終APP_ASSET_REVISIONを1.18.0-r2へ変更するため、旧V1.18.0候補を開いたBrowserでもAsset URLが変わり、新CSS/JSを取得します。
