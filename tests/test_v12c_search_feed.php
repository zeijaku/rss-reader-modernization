<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/validation.php';
function app_common_feed_list(): array { return [['name'=>'A','category'=>'技術','url'=>'https://example.com/a.xml']]; }
function dashboard_widget_decode_config(mixed $v): array { if(!is_string($v))return []; $d=json_decode($v,true); return is_array($d)?$d:[]; }
require dirname(__DIR__).'/app/search_feed.php';
$pass=0;$fail=0;function ck($ok,$m){global $pass,$fail;if($ok){$pass++;echo "PASS: $m\n";}else{$fail++;echo "FAIL: $m\n";}}
ck(search_feed_config_from_input(['search_query'=>'PHP 広島','search_scope'=>'both','search_condition'=>'and','search_limit'=>'10','search_category'=>'all'])!==null,'valid config');
ck(search_feed_config_from_input(['search_query'=>'','search_scope'=>'owned','search_condition'=>'or','search_limit'=>'10','search_category'=>'all'])===null,'empty query rejected');
ck(search_feed_config_from_input(['search_query'=>'x','search_scope'=>'other','search_condition'=>'or','search_limit'=>'10','search_category'=>'all'])===null,'invalid scope rejected');
ck(search_feed_config_from_input(['search_query'=>'x','search_scope'=>'owned','search_condition'=>'or','search_limit'=>'31','search_category'=>'all'])===null,'limit rejected');
$item=['title'=>'広島 PHP 勉強会','description'=>'Web開発','content'=>''];
ck(search_feed_item_matches($item,['PHP','広島'],'and'),'AND match');
ck(!search_feed_item_matches($item,['PHP','東京'],'and'),'AND miss');
ck(search_feed_item_matches($item,['PHP','東京'],'or'),'OR match');
ck(!search_feed_item_matches($item,['Ruby','東京'],'or'),'OR miss');
ck(search_feed_common_categories()===['技術'],'common category');
echo "RESULT: PASS $pass / FAIL $fail\n"; exit($fail?1:0);
