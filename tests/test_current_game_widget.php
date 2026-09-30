<?php
declare(strict_types=1);
putenv('APP_ENV=testing');putenv('APP_DEBUG=false');putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');putenv('DB_DRIVER=sqlite');putenv('DB_TABLE_PREFIX=v140_');
$root=dirname(__DIR__);
foreach (['common/common_conf.php','common/common_db.php','validation.php','dashboard_widget.php','mini_game.php','api.php'] as $file) require_once $root.'/app/'.$file;
$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$pdo->exec('CREATE TABLE v140_dashboard_widget (widget_id INTEGER PRIMARY KEY AUTOINCREMENT,widget_owner INTEGER NOT NULL,widget_location INTEGER,widget_type TEXT,widget_reference_id INTEGER NULL,widget_sort_order INTEGER,widget_width INTEGER,widget_height INTEGER,widget_style TEXT,widget_config TEXT,widget_flag INTEGER,widget_created_at TEXT,widget_updated_at TEXT, UNIQUE(widget_owner,widget_type,widget_reference_id))');
set_db_connection_for_testing($pdo);$checks=0;
function check140(bool $value,string $message):void{global $checks;if(!$value)throw new RuntimeException($message);$checks++;echo 'PASS: '.$message."\n";}
$input=['widget_location'=>'1','widget_style'=>'secondary','widget_width'=>'1','widget_height'=>'1','game_title'=>'Maze Chase','game_type'=>'maze_chase','widget_owner'=>'999'];
$created=api_dispatch('widget.game.create',7,$input);check140($created['status']===201,'Maze created through existing API');$id=$created['body']['data']['widget_id'];
$created2=api_dispatch('widget.game.create',7,$input);check140($created2['status']===201 && $created2['body']['data']['widget_id']!==$id,'real PDO unique index permits multiple Maze Widgets');
$row=$pdo->query('SELECT * FROM v140_dashboard_widget WHERE widget_id='.$id)->fetch();check140($row['widget_owner']===7 && $row['widget_reference_id']===null,'session owner and NULL reference retained with non-default prefix');
$config=dashboard_widget_normalize_row($row);check140($config['widget_config_data']===['schema'=>1,'title'=>'Maze Chase','game'=>'maze_chase'],'existing normalization round-trips Maze config');
check140(api_dispatch('widget.game.update',8,$input+['widget_id'=>(string)$id])['status']===404,'foreign update rejected');
check140(api_dispatch('widget.game.delete',8,['widget_id'=>(string)$id])['status']===404,'foreign delete rejected');
check140(api_dispatch('widget.game.create',0,$input)['status']===401,'unauthenticated creation rejected');
foreach (['maze-chase','../maze_chase','constructor','<script>','falling_blocks'] as $bad){$invalid=$input;$invalid['game_type']=$bad;check140(api_dispatch('widget.game.create',7,$invalid)['status']===422,'unapproved subtype rejected: '.$bad);}
$invalid=$input;$invalid['game_title']=str_repeat('x',33);check140(api_dispatch('widget.game.create',7,$invalid)['status']===422,'oversized title rejected');
$changed=$input;$changed['widget_id']=(string)$id;$changed['game_title']='独自の見出し';$changed['widget_width']='4';check140(api_dispatch('widget.game.update',7,$changed)['status']===200,'owned settings update accepted');
check140(api_dispatch('widget.game.delete',7,['widget_id'=>(string)$id])['status']===200,'owned deletion accepted');
check140((int)$pdo->query('SELECT COUNT(*) FROM v140_dashboard_widget WHERE widget_flag=0')->fetchColumn()===1,'deletion does not affect second widget');
foreach (['icon_quest','lights_out','wire_defense','block_collapse','cursor_field','game_2048','reversi'] as $old){$input['game_type']=$old;check140(api_dispatch('widget.game.create',7,$input)['status']===201,'legacy subtype remains accepted: '.$old);}
echo 'RESULT: PASS '.$checks." / FAIL 0 / SKIP 0\n";
