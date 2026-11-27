<?php
declare(strict_types=1);

function calendar_import_load(): void
{
    calendar_transfer_load_dependencies();
    require_once __DIR__ . '/calendar_source.php';
    require_once __DIR__ . '/calendar_day_mark.php';
    require_once __DIR__ . '/calendar_ics_import_parser.php';
}

/** Read all owner state for optimistic preview. On apply lock rows and owner. */
function calendar_import_state(PDO $pdo,int $owner,bool $lock): array
{
    $suffix=$lock&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
    if ($suffix!=='') {
        $q=$pdo->prepare('SELECT user_id FROM '.db_table_identifier('user_info').' WHERE user_id=? FOR UPDATE');
        $q->execute([$owner]); if ($q->fetchColumn()===false) throw new OutOfBoundsException('Owner not found.');
    }
    $state=[];
    foreach ([
        'sources'=>['calendar_source','calendar_source_owner','calendar_source_id','calendar_source_flag=0',21],
        'events'=>['calendar_event','calendar_event_owner','calendar_event_id','calendar_event_flag=0',501],
        'exceptions'=>['calendar_event_exception','calendar_event_exception_owner','calendar_event_exception_id','calendar_event_exception_flag=0',501],
        'marks'=>['calendar_day_mark','mark_owner','mark_date','1=1',37001],
    ] as $key=>[$table,$field,$order,$where,$limit]) {
        $q=$pdo->prepare('SELECT * FROM '.db_table_identifier($table)." WHERE $field=? AND $where ORDER BY $order LIMIT $limit".$suffix);
        $q->execute([$owner]); $state[$key]=$q->fetchAll(PDO::FETCH_ASSOC);
        if (count($state[$key])===$limit) throw new LengthException('既存Calendarが上限を超えています。');
    }
    return $state;
}

function calendar_import_options(array $input,array $parsed): array
{
    $target=$input['target']??'existing'; $mode=$input['mode']??'add'; $extra=$input['include_extra']??'1';
    $source=app_validate_positive_int($input['source_id']??null);
    if (!in_array($target,['existing','new'],true)||!in_array($mode,['add','update'],true)||!in_array($extra,['0','1'],true)
        || ($target==='existing'&&$source===null)||($target==='new'&&isset($input['source_id'])&&$input['source_id']!=='')) throw new InvalidArgumentException('登録先または登録方式が不正です。');
    $name=null; $color=null;
    if ($target==='new') {
        $name=calendar_source_validate_name($input['name']??$parsed['name']);
        $color=calendar_source_validate_color($input['color']??$parsed['color']);
        if ($name===null||$color===null) throw new InvalidArgumentException('新規Calendar名または色が不正です。');
    }
    return ['target'=>$target,'source_id'=>$target==='existing'?$source:null,'mode'=>$mode,'include_extra'=>$extra==='1','name'=>$name,'color'=>$color];
}

/** Same-content check ignores timestamps and local-only metadata absent in the file. */
function calendar_import_values(array $event,array $repeat,?array $existing,bool $extra): array
{
    $time=$event['time'];
    return ['title'=>$event['title'],'note'=>$event['note'],'start_date'=>$event['start_date'],'end_date'=>$event['end_date'],
        'all_day'=>$time['all_day']?1:0,'start_time'=>$time['start_time'],'end_time'=>$time['end_time'],
        'url'=>$time['url'],'location'=>$time['location'],'repeat_type'=>$repeat['repeat_type'],'repeat_until'=>$repeat['repeat_until'],
        'color'=>$event['color_present']?$event['color']:($existing['calendar_event_color']??'blue'),
        'deadline_highlight'=>$extra&&$time['deadline_highlight']!==null?($time['deadline_highlight']?1:0):(int)($existing['calendar_event_deadline_highlight']??0),
        'reminder'=>$event['reminder_present']?$event['reminder']:($existing['calendar_event_reminder']??'none')];
}

function calendar_import_same(array $row,array $values,string $prefix): bool
{
    foreach ($values as $key=>$value) {
        $current=$row[$prefix.$key]??null;
        if ($value===null ? $current!==null : ($current===null||(string)$current!==(string)$value)) return false;
    }
    return true;
}

function calendar_import_exception_values(array $exception,array $parent,bool $extra): array
{
    if ($exception['kind']==='cancelled') return ['kind'=>'cancelled'];
    $e=$exception['event']; $t=$e['time'];
    return ['kind'=>'override','title'=>$e['title'],'note'=>$e['note'],'start_date'=>$e['start_date'],'end_date'=>$e['end_date'],
        'color'=>$e['color_present']?$e['color']:$parent['color'],'all_day'=>$t['all_day']?1:0,'start_time'=>$t['start_time'],
        'end_time'=>$t['end_time'],'url'=>$t['url'],'location'=>$t['location'],
        'deadline_highlight'=>$extra&&$t['deadline_highlight']!==null?($t['deadline_highlight']?1:0):$parent['deadline_highlight']];
}

/** No absent local event or occurrence is deleted, including in update mode. */
function calendar_import_plan(array $parsed,array $options,array $state): array
{
    $source=null; $hasDefault=false;
    foreach ($state['sources'] as $s) {
        if ((int)$s['calendar_source_default']===1) $hasDefault=true;
        if ((int)$s['calendar_source_id']===$options['source_id']) $source=$s;
    }
    if ($options['target']==='existing'&&$source===null) throw new OutOfBoundsException('Calendar not found.');
    $blocked=$options['target']==='new'&&count($state['sources'])+1+($hasDefault?0:1)>CALENDAR_SOURCE_MAX_ACTIVE;
    $lookup=[]; $byEvent=[]; $activeSeries=0;
    foreach ($state['events'] as $row) {
        if ($row['calendar_event_repeat_type']!=='none') $activeSeries++;
        if ($source!==null&&((int)$row['calendar_event_source_id']===(int)$source['calendar_source_id']
            || ((int)$source['calendar_source_default']===1&&$row['calendar_event_source_id']===null))) {
            $lookup['u:'.$row['calendar_event_ics_uid']][]=$row;
        }
    }
    foreach ($state['exceptions'] as $row) $byEvent[(int)$row['calendar_event_exception_event_id']][$row['calendar_event_exception_original_start_date']]=$row;
    $counts=['add'=>0,'update'=>0,'skip'=>0,'conflict'=>0,'unsupported'=>count($parsed['issues']),'marks_add'=>0,'marks_update'=>0,'marks_skip'=>0];
    $actions=[]; $details=[]; $events=count($state['events']); $series=$activeSeries; $exceptions=count($state['exceptions']);
    foreach ($parsed['series'] as $item) {
        $matches=$lookup['u:'.$item['uid']]??[];
        $existing=count($matches)===1?$matches[0]:null;
        $status='add'; $reason=''; $changes=[];
        $values=calendar_import_values($item['event'],$item['repeat'],$existing,$options['include_extra']);
        $localExceptions=$existing===null?[]:($byEvent[(int)$existing['calendar_event_id']]??[]);
        if (count($matches)>1) { $status='conflict'; $reason='登録先に同一UIDが複数あります。'; }
        elseif ($existing!==null&&$options['mode']==='add') { $status='skip'; $reason='同一UIDが登録済みです。'; }
        else {
            // Existing exceptions must remain valid under the proposed series.
            foreach ($localExceptions as $date=>$exception) {
                if (!in_array($date,calendar_recurrence_occurrence_starts($values['repeat_type'],$values['start_date'],$date,$date,$values['repeat_until']),true)) {
                    $status='conflict'; $reason='既存の個別例外と繰り返し条件が矛盾します。'; break;
                }
            }
            foreach ($item['exceptions'] as $date=>$exception) {
                $v=calendar_import_exception_values($exception,$values,$options['include_extra']);
                if (!isset($localExceptions[$date])||!calendar_import_same($localExceptions[$date],$v,'calendar_event_exception_')) $changes[$date]=$v;
            }
            if ($status!=='conflict'&&$existing!==null) $status=calendar_import_same($existing,$values,'calendar_event_')&&$changes===[]?'skip':'update';
            if (in_array($status,['add','update'],true)) {
                $nextEvents=$events+($existing===null?1:0);
                $nextSeries=$series+($values['repeat_type']!=='none'?1:0)-($existing!==null&&$existing['calendar_event_repeat_type']!=='none'?1:0);
                $nextExceptions=$exceptions+count(array_diff_key($changes,$localExceptions));
                if ($nextEvents>500||$nextSeries>CALENDAR_RECURRENCE_MAX_ACTIVE_SERIES||$nextExceptions>CALENDAR_EXCEPTION_MAX_ACTIVE||$blocked) {
                    $status='conflict'; $reason='Calendar・予定・繰り返し・例外の登録上限を超えます。';
                } else {
                    $events=$nextEvents; $series=$nextSeries; $exceptions=$nextExceptions;
                    $actions[]=['id'=>$existing===null?null:(int)$existing['calendar_event_id'],'uid'=>$item['uid'],'values'=>$values,'exceptions'=>$changes];
                }
            }
            if ($status==='skip') $reason='同じ内容が登録済みです。';
        }
        $counts[$status]++;
        $details[]=['uid'=>$item['uid'],'title'=>$item['event']['title'],'status'=>$status,'reason'=>$reason];
    }
    $markActions=[]; $localMarks=[];
    foreach ($state['marks'] as $m) $localMarks[$m['mark_date']]=['label'=>$m['mark_label'],'note'=>$m['mark_note']];
    if ($options['include_extra']) foreach ($parsed['marks'] as $date=>$mark) {
        if (isset($localMarks[$date])&&($options['mode']==='add'||$localMarks[$date]===$mark)) { $counts['marks_skip']++; continue; }
        $counts[isset($localMarks[$date])?'marks_update':'marks_add']++;
        $markActions[$date]=$mark;
    }
    // Capacity and ambiguous existing data block execution, not merely part of the batch.
    return ['counts'=>$counts,'details'=>$details,'issues'=>$parsed['issues'],'actions'=>$actions,'marks'=>$markActions,
        'can_apply'=>!$blocked&&$counts['conflict']===0&&($actions!==[]||$markActions!==[])];
}

function calendar_import_token(int $owner,string $raw,array $options,array $state): string
{
    if (strlen((string)INI_HASH_KEY)<32) throw new LogicException('Import confirmation key unavailable.');
    // Includes content, target, mode and current owner DB snapshot; no client-supplied plan is trusted.
    return hash_hmac('sha256',json_encode([$owner,hash('sha256',$raw),$options,$state],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),(string)INI_HASH_KEY);
}

function calendar_import_apply(PDO $pdo,int $owner,array $options,array $plan): void
{
    $source=$options['source_id'];
    if ($options['target']==='new') $source=calendar_source_create($owner,$options['name'],$options['color']);
    $table=db_table_identifier('calendar_event');
    foreach ($plan['actions'] as $action) {
        $v=$action['values']; $id=$action['id'];
        if ($id===null) {
            $id=calendar_create_event($owner,$v['title'],$v['start_date'],$v['end_date'],$v['note']);
        } else {
            $current=calendar_lock_owned_event($pdo,$owner,$id);
            if ($current===null) throw new OutOfBoundsException('Calendar event not found.');
            calendar_event_exception_assert_series_change_allowed($pdo,$owner,$current,$v['start_date'],$v['end_date'],
                ['repeat_type'=>$v['repeat_type'],'repeat_until'=>$v['repeat_until']]);
        }
        // All keys come from the validated fixed server schema above.
        $assign=[]; $params=[':id'=>$id,':owner'=>$owner,':source'=>$source,':uid'=>$action['uid'],':updated'=>app_now()];
        foreach ($v as $key=>$value) { $assign[]='calendar_event_'.$key.'=:v_'.$key; $params[':v_'.$key]=$value; }
        $q=$pdo->prepare("UPDATE $table SET ".implode(',',$assign).',calendar_event_source_id=:source,calendar_event_ics_uid=:uid,calendar_event_updated_at=:updated'
            .' WHERE calendar_event_owner=:owner AND calendar_event_id=:id AND calendar_event_flag=0');
        $q->execute($params);
        foreach ($action['exceptions'] as $date=>$exception) {
            $old=calendar_event_exception_lock_row($pdo,$owner,$id,$date);
            $settings=$exception['kind']==='cancelled'?null:[
                'all_day'=>(bool)$exception['all_day'],'start_time'=>$exception['start_time'],'end_time'=>$exception['end_time'],
                'url'=>$exception['url'],'location'=>$exception['location'],'deadline_highlight'=>(bool)$exception['deadline_highlight']];
            calendar_event_exception_save($pdo,$owner,$id,$date,$exception['kind'],$exception['title']??null,
                $exception['start_date']??null,$exception['end_date']??null,$exception['note']??null,$exception['color']??null,$settings,$old);
        }
        calendar_event_reminder_reconcile($pdo,$owner,$id);
    }
    foreach ($plan['marks'] as $date=>$mark) calendar_day_mark_save($owner,$date,$mark['label'],$mark['note']);
}

function api_calendar_transfer_import(string $action,int $owner,array $input): array
{
    calendar_import_load();
    $raw=$input['ics']??null;
    $apply=$action==='calendar.transfer.import';
    $token=$input['preview_token']??null;
    if ($owner<=0||!is_string($raw)||array_diff(array_keys($input),['action','csrf_token','ics','target','source_id','mode','name','color','include_extra','preview_token'])!==[]
        ||($apply&&(!is_string($token)||preg_match('/\A[a-f0-9]{64}\z/D',$token)!==1))) return api_error('validation_error','ICSとプレビュー条件を確認してください。',422);
    $pdo=conn_db();
    if (!calendar_ics_uid_schema_ready($pdo)||!calendar_source_schema_ready($pdo)) return api_error('calendar_transfer_unavailable','Migration 037が必要です。',503);
    if ($pdo->inTransaction()) throw new LogicException('Import requires its own transaction.');
    try {
        $parsed=calendar_import_parse($raw,($input['include_extra']??'1')!=='0'); $options=calendar_import_options($input,$parsed);
        $pdo->beginTransaction();
        $state=calendar_import_state($pdo,$owner,$apply);
        $expected=calendar_import_token($owner,$raw,$options,$state);
        if ($apply&&!hash_equals($expected,$token)) {
            $pdo->rollBack(); return api_error('calendar_import_stale','プレビュー後に条件またはデータが変更されました。再確認してください。',409);
        }
        $plan=calendar_import_plan($parsed,$options,$state);
        if ($apply) {
            if (!$plan['can_apply']) { $pdo->rollBack(); return api_error('calendar_import_blocked','登録可能な変更がないか、競合があります。再確認してください。',409); }
            calendar_import_apply($pdo,$owner,$options,$plan);
        }
        $pdo->commit();
        unset($plan['actions'],$plan['marks']);
        return api_success($plan+['preview_token'=>$apply?null:$expected,'applied'=>$apply,'target'=>$options,'components'=>$parsed['components']]);
    } catch (OutOfBoundsException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return api_error('not_found','Calendarが見つかりません。',404);
    } catch (InvalidArgumentException|DomainException|LengthException|CalendarOccurrenceConflictException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return api_error('calendar_import_invalid','ICSの形式・登録条件・上限・競合を確認してください。',422);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
