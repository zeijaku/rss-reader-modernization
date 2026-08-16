<?php

declare(strict_types=1);

function search_feed_defaults(): array
{
    return ['schema'=>1,'query'=>'','scope'=>'owned','condition'=>'or','limit'=>10,'category'=>'all'];
}
function search_feed_validate_query(mixed $v): ?string
{
    if (!is_string($v)) return null;
    $v=trim(preg_replace('/\s+/u',' ',$v) ?? '');
    return app_validate_text($v,128,false);
}
function search_feed_validate_scope(mixed $v): ?string
{
    return is_string($v) && in_array($v,['owned','common','both'],true) ? $v : null;
}
function search_feed_validate_condition(mixed $v): ?string
{
    return is_string($v) && in_array($v,['or','and'],true) ? $v : null;
}
function search_feed_validate_limit(mixed $v): ?int
{
    $n=app_validate_positive_int($v); return $n!==null && $n<=30 ? $n : null;
}
function search_feed_validate_category(mixed $v): ?string
{
    if (!is_string($v) || app_text_length($v)>32) return null;
    return $v==='all' || in_array($v,search_feed_common_categories(),true) ? $v : null;
}
/** @return list<array<string,mixed>> */
function search_feed_common_catalog(): array
{
    $out=[];
    foreach(app_common_feed_list() as $r){
        if(!is_array($r)||(($r['discovery']??false)===true))continue;
        $out[]=$r;
    }
    return $out;
}
function search_feed_common_categories(): array
{
    $out=[]; foreach(search_feed_common_catalog() as $r){$c=(string)($r['category']??''); if($c!==''&&!in_array($c,$out,true))$out[]=$c;} return $out;
}
function search_feed_config_from_input(array $in): ?array
{
    $q=search_feed_validate_query($in['search_query']??null);
    $s=search_feed_validate_scope($in['search_scope']??null);
    $c=search_feed_validate_condition($in['search_condition']??null);
    $l=search_feed_validate_limit($in['search_limit']??null);
    $g=search_feed_validate_category($in['search_category']??'all');
    if($q===null||$q===''||$s===null||$c===null||$l===null||$g===null)return null;
    return ['schema'=>1,'query'=>$q,'scope'=>$s,'condition'=>$c,'limit'=>$l,'category'=>$g];
}
function search_feed_config_from_storage(mixed $v): array
{
    $d=search_feed_defaults(); $x=dashboard_widget_decode_config($v);
    return [
      'schema'=>1,
      'query'=>search_feed_validate_query($x['query']??null) ?: $d['query'],
      'scope'=>search_feed_validate_scope($x['scope']??null) ?? $d['scope'],
      'condition'=>search_feed_validate_condition($x['condition']??null) ?? $d['condition'],
      'limit'=>search_feed_validate_limit($x['limit']??null) ?? $d['limit'],
      'category'=>search_feed_validate_category($x['category']??null) ?? $d['category'],
    ];
}
function search_feed_terms(string $query): array
{
    $parts=preg_split('/[\s　]+/u',trim($query),-1,PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_unique(array_slice($parts,0,12)));
}
function search_feed_text_contains(string $haystack,string $needle): bool
{
    if (function_exists('mb_stripos')) return mb_stripos($haystack,$needle,0,'UTF-8')!==false;
    return stripos($haystack,$needle)!==false;
}
function search_feed_item_matches(array $item,array $terms,string $condition): bool
{
    $text=implode("\n",[(string)($item['title']??''),(string)($item['description']??''),(string)($item['content']??''),implode(' ',is_array($item['categories']??null)?$item['categories']:[])]);
    if($terms===[])return false;
    $hits=0; foreach($terms as $t){if(search_feed_text_contains($text,$t))$hits++;}
    return $condition==='and' ? $hits===count($terms) : $hits>0;
}
function search_feed_owned_sources(int $ownerId): array
{
    $stmt=conn_db()->prepare('SELECT content_id, content_owner, content_value FROM '.db_table_identifier('content').' WHERE content_owner=:owner AND content_flag=0 ORDER BY content_id ASC');
    $stmt->execute([':owner'=>$ownerId]); $out=[];
    foreach($stmt->fetchAll() as $r){if(!is_array($r))continue; $url=app_validate_feed_url($r['content_value']??null); $id=app_validate_positive_int($r['content_id']??null); if($url!==null&&$id!==null)$out[]=['source_id'=>$id,'url'=>$url,'name'=>''];}
    return $out;
}
function search_feed_common_sources(int $ownerId,string $category): array
{
    $out=[]; $i=1;
    foreach(search_feed_common_catalog() as $r){if($category!=='all'&&($r['category']??'')!==$category)continue; $url=app_validate_feed_url($r['url']??null); if($url===null)continue; $out[]=['source_id'=>900000000+$i,'url'=>$url,'name'=>(string)($r['name']??'')]; $i++;}
    return $out;
}
function search_feed_owned_widget(int $ownerId,int $widgetId): ?array
{
    $stmt=conn_db()->prepare('SELECT * FROM '.db_table_identifier('dashboard_widget')." WHERE widget_id=:id AND widget_owner=:owner AND widget_type='search' AND widget_flag=0");
    $stmt->execute([':id'=>$widgetId,':owner'=>$ownerId]); $r=$stmt->fetch(); return is_array($r)?$r:null;
}
function search_feed_create(int $ownerId,int $location,string $style,int $width,array $config,int $height=1): int
{
    if(dashboard_widget_validate_height($height)===null)throw new InvalidArgumentException('Search Feed height is invalid.');
    $pdo=conn_db(); $now=gmdate('Y-m-d H:i:s');
    $pdo->beginTransaction(); try{$sort=dashboard_widget_next_sort_order($pdo,$ownerId,$location); $st=$pdo->prepare('INSERT INTO '.db_table_identifier('dashboard_widget').' (widget_owner,widget_location,widget_type,widget_reference_id,widget_sort_order,widget_width,widget_height,widget_style,widget_config,widget_flag,widget_created_at,widget_updated_at) VALUES (:owner,:location,\'search\',NULL,:sort,:width,:height,:style,:config,0,:created,:updated)');$st->execute([':owner'=>$ownerId,':location'=>$location,':sort'=>$sort,':width'=>$width,':height'=>$height,':style'=>$style,':config'=>dashboard_widget_encode_config($config),':created'=>$now,':updated'=>$now]);$id=(int)$pdo->lastInsertId();$pdo->commit();return $id;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function search_feed_update(int $ownerId,int $widgetId,string $style,int $width,array $config,int $height=1): bool
{
    if(dashboard_widget_validate_height($height)===null)throw new InvalidArgumentException('Search Feed height is invalid.');
    if(search_feed_owned_widget($ownerId,$widgetId)===null)return false;
    $st=conn_db()->prepare('UPDATE '.db_table_identifier('dashboard_widget').' SET widget_width=:width,widget_height=:height,widget_style=:style,widget_config=:config,widget_updated_at=:updated WHERE widget_id=:id AND widget_owner=:owner AND widget_type=\'search\' AND widget_flag=0');
    $st->execute([':width'=>$width,':height'=>$height,':style'=>$style,':config'=>dashboard_widget_encode_config($config),':updated'=>gmdate('Y-m-d H:i:s'),':id'=>$widgetId,':owner'=>$ownerId]);return $st->rowCount()===1;
}
function search_feed_delete(int $ownerId,int $widgetId): bool
{
    $st=conn_db()->prepare('UPDATE '.db_table_identifier('dashboard_widget').' SET widget_flag=1,widget_updated_at=:updated WHERE widget_id=:id AND widget_owner=:owner AND widget_type=\'search\' AND widget_flag=0');$st->execute([':updated'=>gmdate('Y-m-d H:i:s'),':id'=>$widgetId,':owner'=>$ownerId]);return $st->rowCount()===1;
}
function search_feed_execute(int $ownerId,int $widgetId): array
{
    $row=search_feed_owned_widget($ownerId,$widgetId); if($row===null)return ['ok'=>false,'code'=>'not_found'];
    $cfg=search_feed_config_from_storage($row['widget_config']??null); if($cfg['query']==='')return ['ok'=>false,'code'=>'invalid_config'];
    $sources=[]; if(in_array($cfg['scope'],['owned','both'],true))$sources=array_merge($sources,search_feed_owned_sources($ownerId)); if(in_array($cfg['scope'],['common','both'],true))$sources=array_merge($sources,search_feed_common_sources($ownerId,$cfg['category']));
    $seenUrl=[];$unique=[];foreach($sources as $s){if(isset($seenUrl[$s['url']]))continue;$seenUrl[$s['url']]=1;$unique[]=$s;}
    $terms=search_feed_terms($cfg['query']);$items=[];$failed=0;$service=FeedFetchService::fromRuntimeConfiguration();
    foreach($unique as $s){try{$source=FeedSource::fromValidatedValues((int)$s['source_id'],$ownerId,(string)$s['url']);$loaded=$service->load($source);if(($loaded['ok']??false)!==true){$failed++;continue;}$rawFeed=is_array($loaded['result_feed']??null)?$loaded['result_feed']:[];$effective=is_string($loaded['effective_url']??null)?$loaded['effective_url']:(string)$s['url'];$feed=api_safe_feed_payload($rawFeed,$effective);$channel=is_array($feed['channel']??null)?$feed['channel']:[];foreach(is_array($feed['item']??null)?$feed['item']:[] as $item){if(!is_array($item)||!search_feed_item_matches($item,$terms,$cfg['condition']))continue;$link=(string)($item['link']??'');$key=hash('sha256',$link."\n".(string)($item['title']??''));if(isset($items[$key]))continue;$item['source_title']=(string)($channel['title']??$s['name']);$items[$key]=$item;}}catch(Throwable){$failed++;}}
    $items=array_slice(array_values($items),0,$cfg['limit']);
    return ['ok'=>true,'query'=>$cfg['query'],'items'=>$items,'source_count'=>count($unique),'failed_count'=>$failed,'limit'=>$cfg['limit']];
}

/** @return list<array{source_id:int,name:string,category:string,url:string}> */
function blind_spot_feed_catalog(): array
{
    $out=[];$index=0;
    foreach(app_common_feed_list() as $feed){
        if(!is_array($feed)||(($feed['discovery']??false)!==true))continue;
        $name=app_validate_text($feed['name']??null,128,false);
        $category=app_validate_text($feed['category']??null,32,false);
        $url=app_validate_feed_url($feed['url']??null);
        if($name===null||$category===null||$url===null)continue;
        $index++;
        $out[]=['source_id'=>910000000+$index,'name'=>$name,'category'=>$category,'url'=>$url];
    }
    return $out;
}

/** @return array<string,list<array{source_id:int,name:string,category:string,url:string}>> */
function blind_spot_feed_groups(): array
{
    $groups=[];
    foreach(blind_spot_feed_catalog() as $feed){
        $category=$feed['category'];
        if(!isset($groups[$category]))$groups[$category]=[];
        $groups[$category][]=$feed;
    }
    return $groups;
}

/** @return array<string,mixed>|null */
function blind_spot_owned_widget(int $ownerId,int $widgetId): ?array
{
    $stmt=conn_db()->prepare('SELECT * FROM '.db_table_identifier('dashboard_widget')." WHERE widget_id=:id AND widget_owner=:owner AND widget_type='blind_spot' AND widget_flag=0");
    $stmt->execute([':id'=>$widgetId,':owner'=>$ownerId]);
    $row=$stmt->fetch();
    return is_array($row)?$row:null;
}

/**
 * V1.16-C: Rotate away from the previous category and suppress recently shown
 * articles while keeping the V1.16-B request budget and safe Feed pipeline.
 *
 * @return array<string,mixed>
 */
function blind_spot_execute(int $ownerId,int $widgetId): array
{
    $row=blind_spot_owned_widget($ownerId,$widgetId);
    if($row===null)return ['ok'=>false,'code'=>'not_found'];
    $config=dashboard_widget_blind_spot_config_from_storage($row['widget_config']??null);
    $groups=blind_spot_feed_groups();
    $categories=array_keys($groups);
    if($categories===[])return ['ok'=>true,'category'=>'','items'=>[],'sources'=>[],'source_count'=>0,'failed_count'=>0,'category_count'=>0];

    $previousCategory=(string)($config['last_category']??'');
    $categoryCandidates=$categories;
    if(count($categories)>1&&$previousCategory!==''){
        $withoutPrevious=array_values(array_filter(
            $categories,
            static fn(string $candidate): bool=>$candidate!==$previousCategory
        ));
        if($withoutPrevious!==[])$categoryCandidates=$withoutPrevious;
    }
    try{$category=$categoryCandidates[random_int(0,count($categoryCandidates)-1)];}
    catch(Throwable){$category=$categoryCandidates[0];}

    $recentKeys=[];
    foreach(is_array($config['recent_items']??null)?$config['recent_items']:[] as $entry){
        if(!is_array($entry))continue;
        $key=(string)($entry['key']??'');
        if(preg_match('/^[a-f0-9]{64}$/',$key)===1)$recentKeys[$key]=true;
    }

    $sources=$groups[$category]??[];
    if(count($sources)>1)shuffle($sources);
    $service=FeedFetchService::fromRuntimeConfiguration();
    $candidates=[];$candidateKeys=[];$failed=0;$sourceNames=[];$attempted=0;

    foreach($sources as $sourceRow){
        if(count($candidates)>=3)break;
        $attempted++;
        try{
            $source=FeedSource::fromValidatedValues((int)$sourceRow['source_id'],$ownerId,(string)$sourceRow['url']);
            $loaded=$service->load($source);
            if(($loaded['ok']??false)!==true){$failed++;continue;}
            $rawFeed=is_array($loaded['result_feed']??null)?$loaded['result_feed']:[];
            $effective=is_string($loaded['effective_url']??null)?$loaded['effective_url']:(string)$sourceRow['url'];
            $feed=api_safe_feed_payload($rawFeed,$effective);
            $channel=is_array($feed['channel']??null)?$feed['channel']:[];
            $sourceTitle=trim((string)($channel['title']??''));
            if($sourceTitle==='')$sourceTitle=(string)$sourceRow['name'];
            if(!in_array($sourceTitle,$sourceNames,true))$sourceNames[]=$sourceTitle;
            foreach(is_array($feed['item']??null)?$feed['item']:[] as $item){
                if(!is_array($item))continue;
                $title=trim((string)($item['title']??''));
                $link=trim((string)($item['link']??''));
                if($title===''||$link==='')continue;
                $key=hash('sha256',$link."\n".$title);
                if(isset($candidateKeys[$key]))continue;
                $candidateKeys[$key]=true;
                if(isset($recentKeys[$key]))continue;
                $item['source_title']=$sourceTitle;
                $candidates[]=['key'=>$key,'item'=>$item];
                if(count($candidates)>=12)break;
            }
        }catch(Throwable){$failed++;}
    }

    if(count($candidates)>1)shuffle($candidates);
    $selected=array_slice($candidates,0,3);
    $items=[];$selectedKeys=[];
    foreach($selected as $entry){
        if(!is_array($entry)||!is_array($entry['item']??null))continue;
        $items[]=$entry['item'];
        $selectedKeys[]=(string)$entry['key'];
    }

    if(!dashboard_widget_blind_spot_remember($ownerId,$widgetId,$category,$selectedKeys)){
        return ['ok'=>false,'code'=>'not_found'];
    }

    return [
        'ok'=>true,
        'category'=>$category,
        'items'=>$items,
        'sources'=>$sourceNames,
        'source_count'=>$attempted,
        'failed_count'=>$failed,
        'category_count'=>count($categories),
    ];
}
