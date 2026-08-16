<?php

declare(strict_types=1);

/**
 * Search Feed / Blind Spot で利用する共通RSS Catalog。
 * URLは実行時にも既存のFeed URL検証・SSRF対策を通す。
 * discovery=true はBlind Spot専用。既存Search Feedからは除外する。
 *
 * @return list<array{name:string,category:string,url:string,discovery?:bool}>
 */
function app_common_feed_list(): array
{
    return [
        /* Existing Search Feed catalog. */
        ['name' => 'PHP.net Releases', 'category' => 'PHP・Web開発', 'url' => 'https://www.php.net/releases/feed.php'],
        ['name' => 'GitHub Blog', 'category' => '技術', 'url' => 'https://github.blog/feed/'],
        ['name' => 'MDN Blog', 'category' => 'PHP・Web開発', 'url' => 'https://developer.mozilla.org/en-US/blog/rss.xml'],
        ['name' => 'Publickey', 'category' => '技術', 'url' => 'https://www.publickey1.jp/atom.xml'],
        ['name' => 'JVN 新着情報', 'category' => 'セキュリティ', 'url' => 'https://jvn.jp/rss/jvn.rdf'],

        /* V1.16-C-R1 Blind Spot / Discovery catalog (Japan priority). */
        ['name' => 'JAXA プレスリリース', 'category' => '宇宙・天文', 'url' => 'https://www.jaxa.jp/rss/press_j.rdf', 'discovery' => true],
        ['name' => 'JAXA 更新情報', 'category' => '宇宙・天文', 'url' => 'https://www.jaxa.jp/rss/new_j.rdf', 'discovery' => true],

        ['name' => '日本物理学会誌', 'category' => '物理・計測', 'url' => 'https://www.jstage.jst.go.jp/AF05S010NewRssDld?btnaction=JT0041&rssLang=ja&sryCd=butsuri', 'discovery' => true],
        ['name' => '計測と制御', 'category' => '物理・計測', 'url' => 'https://www.jstage.jst.go.jp/AF05S010NewRssDld?btnaction=JT0041&rssLang=ja&sryCd=sicejl', 'discovery' => true],

        ['name' => 'NIMS News', 'category' => '化学・ナノ', 'url' => 'https://www.nims.go.jp/news/newsall.xml', 'discovery' => true],
        ['name' => '産総研 関西センター', 'category' => '化学・ナノ', 'url' => 'https://www.aist.go.jp/ctl/module/mid/17192/tid/188663/rss.xml', 'discovery' => true],

        ['name' => '厚生労働省 新着情報', 'category' => '生物・健康', 'url' => 'https://www.mhlw.go.jp/stf/news.rdf', 'discovery' => true],
        ['name' => 'UMIN 臨床試験登録情報', 'category' => '生物・健康', 'url' => 'https://www.umin.ac.jp/rss/ctr.xml', 'discovery' => true],

        ['name' => '農林水産省 新着情報', 'category' => '農業・食料', 'url' => 'https://www.maff.go.jp/rss.xml', 'discovery' => true],
        ['name' => '農林水産研究情報総合センター', 'category' => '農業・食料', 'url' => 'https://www.affrc.maff.go.jp/rss.xml', 'discovery' => true],

        ['name' => 'JAMSTEC 黒潮親潮ウォッチ', 'category' => '海洋', 'url' => 'https://www.jamstec.go.jp/aplinfo/kowatch/?feed=rss2&lang=ja', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 水産業', 'category' => '海洋', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000033.xml', 'discovery' => true],

        ['name' => '国立環境研究所 地球環境研究センター', 'category' => '環境・自然科学', 'url' => 'https://www.cger.nies.go.jp/cgernews/rss/index.xml', 'discovery' => true],
        ['name' => '環境省 脱炭素ポータル', 'category' => '環境・自然科学', 'url' => 'https://ondankataisaku.env.go.jp/carbon_neutral/rss.xml', 'discovery' => true],

        ['name' => '日本火山学会講演予稿集', 'category' => '地質・火山', 'url' => 'https://www.jstage.jst.go.jp/AF05S010NewRssDld?btnaction=JT0041&rssLang=ja&sryCd=vsj', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 鉱業', 'category' => '地質・火山', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000034.xml', 'discovery' => true],

        ['name' => '気象庁 防災情報XML', 'category' => '地震・防災', 'url' => 'https://www.data.jma.go.jp/developer/xml/feed/extra.xml', 'discovery' => true],
        ['name' => '国土交通省 災害・防災情報', 'category' => '地震・防災', 'url' => 'https://www.mlit.go.jp/saigai.rdf', 'discovery' => true],

        ['name' => '国土交通省 新着情報', 'category' => '建築・都市', 'url' => 'https://www.mlit.go.jp/index.rdf', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 建築・住宅', 'category' => '建築・都市', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000023.xml', 'discovery' => true],

        ['name' => '国土交通省 プレスリリース', 'category' => '交通・航空', 'url' => 'https://www.mlit.go.jp/pressrelease.rdf', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 航空', 'category' => '交通・航空', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000041.xml', 'discovery' => true],

        ['name' => '産総研 福島再生可能エネルギー研究所', 'category' => 'エネルギー・技術', 'url' => 'https://www.aist.go.jp/ctl/module/mid/2064/tid/20958/rss.xml', 'discovery' => true],
        ['name' => 'JST トピックス', 'category' => 'エネルギー・技術', 'url' => 'https://www.jst.go.jp/rss/topics.xml', 'discovery' => true],

        ['name' => 'NIMS News', 'category' => '材料・製造', 'url' => 'https://www.nims.go.jp/news/news.xml', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 工業', 'category' => '材料・製造', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000035.xml', 'discovery' => true],

        ['name' => 'e-Gov パブリック・コメント 司法', 'category' => '法律・制度', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000013.xml', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 民事', 'category' => '法律・制度', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000014.xml', 'discovery' => true],

        ['name' => '日本考古学', 'category' => '歴史・考古学', 'url' => 'https://www.jstage.jst.go.jp/AF05S010NewRssDld?btnaction=JT0041&rssLang=ja&sryCd=nihonkokogaku1994', 'discovery' => true],
        ['name' => '東京国立博物館 特別展', 'category' => '歴史・考古学', 'url' => 'https://www.tnm.jp/modules/r_exhibition/index.php?cid=1&controller=ctg_feed&lang=ja', 'discovery' => true],

        ['name' => '音楽学', 'category' => '美術・音楽', 'url' => 'https://www.jstage.jst.go.jp/AF05S010NewRssDld?btnaction=JT0041&rssLang=ja&sryCd=ongakugaku', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 文化', 'category' => '美術・音楽', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000029.xml', 'discovery' => true],

        ['name' => '国立教育政策研究所 新着情報', 'category' => '教育', 'url' => 'https://www.nier.go.jp/02_news/rss.xml', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 教育', 'category' => '教育', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000028.xml', 'discovery' => true],

        ['name' => '国土地理院 新着情報', 'category' => '地図・地理', 'url' => 'https://www.gsi.go.jp/index.rdf', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 土地', 'category' => '地図・地理', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000019.xml', 'discovery' => true],

        ['name' => '総務省統計局 新着情報', 'category' => '統計・社会', 'url' => 'https://www.stat.go.jp/whatsnew/news.rdf', 'discovery' => true],
        ['name' => 'e-Gov パブリック・コメント 統計', 'category' => '統計・社会', 'url' => 'https://public-comment.e-gov.go.jp/rss/pcm_list_0000000010.xml', 'discovery' => true],

        ['name' => 'JICA ニュース', 'category' => '国際・世界', 'url' => 'https://www.jica.go.jp/feed/jicanews.xml', 'discovery' => true],
        ['name' => 'JICA 緒方貞子平和開発研究所', 'category' => '国際・世界', 'url' => 'https://www.jica.go.jp/jica-ri/ja/news/rss/rss.xml', 'discovery' => true],
    ];
}
