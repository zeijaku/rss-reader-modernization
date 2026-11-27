<?php
declare(strict_types=1);

require_once __DIR__ . '/calendar_ics_parser.php';

const CALENDAR_IMPORT_MAX_LINES = 200000;
const CALENDAR_IMPORT_MAX_PROPERTIES = 80000;
const CALENDAR_IMPORT_MAX_MARKS = 37000;

/** Single pass: a literal backslash followed by n must not become a newline. */
function calendar_import_text(string $text): string
{
    return preg_replace_callback('/\\\\([nN,;\\\\])/', static fn(array $m): string =>
        strtolower($m[1]) === 'n' ? "\n" : $m[1], $text);
}

/** Strict component boundaries; nested VALARM properties never shadow VEVENT. */
function calendar_import_components(string $raw): array
{
    if ($raw === '' || strlen($raw) > CALENDAR_TRANSFER_MAX_BYTES || preg_match('//u', $raw) !== 1
        || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $raw)) {
        throw new InvalidArgumentException('ICSの容量・文字コード・制御文字を確認してください。');
    }
    if (str_starts_with($raw, "\xef\xbb\xbf")) $raw = substr($raw, 3);
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);
    if (count($lines) > CALENDAR_IMPORT_MAX_LINES) throw new LengthException('ICSの行数上限を超えています。');
    $stack = []; $root = null; $components = 0; $properties = 0;
    $consume = static function (string $line) use (&$stack, &$root, &$components, &$properties): void {
        if ($line === '') return;
        if (strlen($line) > 32768) throw new LengthException('ICSのプロパティが長すぎます。');
        if (preg_match('/\A(BEGIN|END):([A-Z0-9-]+)\z/i', $line, $m)) {
            $type = strtoupper($m[2]);
            if (strtoupper($m[1]) === 'BEGIN') {
                if (count($stack) >= 5 || ++$components > 12000 || ($stack === [] && ($root !== null || $type !== 'VCALENDAR'))) {
                    throw new InvalidArgumentException('ICSのコンポーネント構造が不正です。');
                }
                $stack[] = ['type'=>$type,'props'=>[],'children'=>[]];
            } else {
                $node = array_pop($stack);
                if ($node === null || $node['type'] !== $type) throw new InvalidArgumentException('ICSのBEGIN/ENDが不一致です。');
                if ($stack === []) $root = $node;
                else $stack[count($stack)-1]['children'][] = $node;
            }
            return;
        }
        if ($stack === []) throw new InvalidArgumentException('VCALENDAR外のデータがあります。');
        if (++$properties > CALENDAR_IMPORT_MAX_PROPERTIES) throw new LengthException('ICSのプロパティ数上限を超えています。');
        // A colon inside a quoted parameter value is not the property delimiter.
        if (!preg_match('/\A([A-Za-z0-9-]+)((?:;[A-Za-z0-9-]+=(?:"[^"]*"|[^;:"]*))*)\:(.*)\z/D', $line, $m)) {
            throw new InvalidArgumentException('ICSのプロパティ形式が不正です。');
        }
        $params = [];
        preg_match_all('/;([A-Za-z0-9-]+)=("[^"]*"|[^;:"]*)/', $m[2], $matches, PREG_SET_ORDER);
        foreach ($matches as $p) {
            $key = strtoupper($p[1]);
            if (isset($params[$key])) throw new InvalidArgumentException('ICSのパラメータが重複しています。');
            $params[$key] = trim($p[2], '"');
        }
        $stack[count($stack)-1]['props'][strtoupper($m[1])][] = ['value'=>$m[3],'params'=>$params];
    };
    $pending = null;
    foreach ($lines as $line) {
        if (str_starts_with($line, ' ') || str_starts_with($line, "\t")) {
            if ($pending === null) throw new InvalidArgumentException('ICSの折り返しが不正です。');
            $pending .= substr($line,1);
            if (strlen($pending)>32768) throw new LengthException('ICSのプロパティが長すぎます。');
        } else {
            if ($pending !== null) $consume($pending);
            $pending = $line;
        }
    }
    if ($pending !== null) $consume($pending);
    if ($stack !== [] || $root === null || calendar_import_value($root['props'],'VERSION') !== '2.0'
        || !in_array(calendar_import_value($root['props'],'METHOD'),[null,'PUBLISH'],true)) {
        throw new InvalidArgumentException('単一のiCalendar 2.0ファイルを指定してください（会議招待・取消メッセージは非対応）。');
    }
    return $root;
}

function calendar_import_prop(array $props, string $key): ?array
{
    if (count($props[$key] ?? []) > 1) throw new DomainException($key . ' が重複しています。');
    return $props[$key][0] ?? null;
}
function calendar_import_value(array $props, string $key): ?string
{
    return calendar_import_prop($props,$key)['value'] ?? null;
}
function calendar_import_text_value(array $props, string $key, string $default=''): string
{
    return calendar_import_text(calendar_import_value($props,$key) ?? $default);
}
function calendar_import_datetime(array $p, DateTimeZone $fallback): array
{
    $dt = calendar_ics_datetime($p,$fallback);
    if ($dt === null || ($dt['all_day'] && isset($p['params']['TZID']))
        || (isset($p['params']['VALUE']) && $p['params']['VALUE'] !== ($dt['all_day']?'DATE':'DATE-TIME'))
        || (isset($p['params']['TZID']) && str_ends_with($p['value'],'Z'))
        || array_diff(array_keys($p['params']),['VALUE','TZID']) !== []) {
        throw new DomainException('日時またはタイムゾーンが非対応です。');
    }
    return $dt;
}

/** Validate data without truncation. Date values remain civil dates. */
function calendar_import_event(array $node): array
{
    $p = $node['props'];
    $tz = new DateTimeZone('Asia/Tokyo');
    foreach (['DTSTART','DTEND','DURATION','SUMMARY','DESCRIPTION','LOCATION','URL','RRULE','RECURRENCE-ID','STATUS'] as $key) calendar_import_prop($p,$key);
    if (isset($p['RDATE']) || isset($p['EXRULE']) || isset($p['RECURRENCE-ID'][0]['params']['RANGE'])) {
        throw new DomainException('RDATE・EXRULE・RANGEは非対応です。');
    }
    $start = calendar_import_datetime(calendar_import_prop($p,'DTSTART') ?? throw new DomainException('DTSTARTがありません。'),$tz);
    $end = calendar_import_prop($p,'DTEND');
    $duration = calendar_import_value($p,'DURATION');
    if ($end !== null && $duration !== null) throw new DomainException('DTENDとDURATIONが重複しています。');
    if ($end !== null) {
        $endValue = calendar_import_datetime($end,$start['tz']);
        if ($endValue['all_day'] !== $start['all_day'] || $endValue['dt'] <= $start['dt']) throw new DomainException('DTENDが不正です。');
        $finish = $endValue['dt'];
    } elseif ($duration !== null) {
        if (strlen($duration)>32 || !preg_match('/\AP(?:[0-9]{1,3}W|(?:[0-9]{1,3}D)?(?:T(?:[0-9]{1,4}H)?(?:[0-9]{1,5}M)?(?:[0-9]{1,6}S)?)?)\z/D',$duration)
            || ($start['all_day'] && str_contains($duration,'T'))) throw new DomainException('DURATIONが非対応です。');
        try { $finish=$start['dt']->add(new DateInterval($duration)); } catch (Throwable $e) { throw new DomainException('DURATIONが不正です。'); }
        if ($finish <= $start['dt']) throw new DomainException('DURATIONが不正です。');
    } else $finish=$start['all_day']?$start['dt']->modify('+1 day'):$start['dt'];
    $local=$start['all_day']?$start['dt']:$start['dt']->setTimezone($tz);
    $finish=$start['all_day']?$finish->modify('-1 day'):$finish->setTimezone($tz);
    $sd=$local->format('Y-m-d'); $ed=$finish->format('Y-m-d');
    $st=$start['all_day']?null:$local->format('H:i:s');
    $et=$start['all_day']||($end===null&&$duration===null)?null:$finish->format('H:i:s');
    $storedEnd=calendar_import_value($p,'X-RSSREADER-END-TIME');
    if ($storedEnd !== null) {
        if ($start['all_day'] || ($storedEnd !== 'NONE' && calendar_event_time_validate_clock($storedEnd) !== $storedEnd)) throw new DomainException('独自終了時刻が不正です。');
        if ($storedEnd !== 'NONE' && $storedEnd !== ($et ?? $st)) throw new DomainException('独自終了時刻が標準日時と矛盾しています。');
        if ($storedEnd === 'NONE' && $et !== null && !($sd !== $ed && $et === '23:59:59')) throw new DomainException('独自終了時刻が標準日時と矛盾しています。');
        $et=$storedEnd==='NONE'?null:$storedEnd;
    }
    $title=calendar_validate_event_title(calendar_import_text_value($p,'SUMMARY','（無題）'));
    $note=calendar_validate_event_note(calendar_import_text_value($p,'DESCRIPTION'));
    $customColor=calendar_import_value($p,'X-RSSREADER-COLOR');
    $standardColor=calendar_import_value($p,'COLOR');
    $color=calendar_event_color_validate($customColor??strtolower($standardColor??'blue'));
    // Standard named colors outside the local palette are not silently mapped.
    if ($title===null || $note===null || $color===null) throw new DomainException('タイトル・メモ・色が登録可能範囲外です。');
    $highlight=calendar_import_value($p,'X-RSSREADER-DEADLINE');
    $settings=calendar_event_time_settings($start['all_day'],$st,$et,calendar_import_value($p,'URL'),$sd,$ed,$highlight,calendar_import_text_value($p,'LOCATION'));
    if ($settings===null) throw new DomainException('予定の日時・URL・場所・締切が登録可能範囲外です。');
    $reminder=calendar_import_reminder($node,$start['all_day']);
    return ['title'=>$title,'note'=>$note,'start_date'=>$sd,'end_date'=>$ed,'color'=>$color,
        'color_present'=>$customColor!==null||$standardColor!==null,'time'=>$settings,'reminder'=>$reminder,
        'reminder_present'=>isset($p['X-RSSREADER-REMINDER'])||$node['children']!==[],
        'start_original'=>$start];
}

function calendar_import_reminder(array $node,bool $allDay): string
{
    $custom=calendar_import_value($node['props'],'X-RSSREADER-REMINDER');
    if ($custom!==null && calendar_event_reminder_validate($custom)===null) throw new DomainException('独自リマインダーが不正です。');
    $alarms=$node['children'];
    if ($alarms===[]) {
        if ($custom!==null && $custom!=='none') throw new DomainException('リマインダー属性とVALARMが矛盾しています。');
        return 'none';
    }
    if (count($alarms)!==1 || $alarms[0]['type']!=='VALARM' || $alarms[0]['children']!==[]) throw new DomainException('複数または非対応アラームがあります。');
    $p=$alarms[0]['props'];
    $trigger=calendar_import_prop($p,'TRIGGER');
    if (calendar_import_value($p,'ACTION')!=='DISPLAY' || $trigger===null || isset($p['REPEAT']) || isset($p['DURATION'])
        || !in_array($trigger['params']['RELATED']??'START',['START'],true)
        || !in_array($trigger['params']['VALUE']??'DURATION',['DURATION'],true)
        || array_diff(array_keys($trigger['params']),['RELATED','VALUE'])!==[]) throw new DomainException('アラーム形式が非対応です。');
    $map=$allDay?['PT9H'=>'at_time','PT8H50M'=>'10m','PT8H30M'=>'30m','PT8H'=>'1h','-PT15H'=>'1d']:
        ['PT0S'=>'at_time','PT0M'=>'at_time','-PT10M'=>'10m','-PT30M'=>'30m','-PT1H'=>'1h','-PT60M'=>'1h','-P1D'=>'1d','-PT24H'=>'1d'];
    $r=$map[$trigger['value']]??null;
    if ($r===null || ($custom!==null&&$custom!==$r)) throw new DomainException('アラーム時刻が非対応または矛盾しています。');
    return $r;
}

/** Only series whose civil recurrence is equivalent in Tokyo are accepted. */
function calendar_import_repeat(array $p,array $event): array
{
    $type='none'; $until=null;
    if (isset($p['RRULE'])) {
        $rule=calendar_ics_rrule($p);
        // The subscription parser tolerates numeric coercion; writes must not.
        foreach (explode(';',strtoupper((string)calendar_import_value($p,'RRULE'))) as $part) {
            if (str_starts_with($part,'INTERVAL=') && !preg_match('/\AINTERVAL=0*1\z/D',$part)) {
                throw new DomainException('INTERVALが非対応です。');
            }
        }
        if ($rule===null || isset($rule['invalid']) || array_diff(array_keys($rule),['FREQ','INTERVAL','COUNT','UNTIL','WKST'])!==[]
            || $rule['INTERVAL']!==1 || (isset($rule['WKST'])&&!in_array($rule['WKST'],['MO','TU','WE','TH','FR','SA','SU'],true))
            || ($rule['COUNT']!==null&&isset($rule['UNTIL']))) throw new DomainException('複雑な繰り返し条件は非対応です。');
        $type=strtolower($rule['FREQ']);
        $original=$event['start_original'];
        if (!$original['all_day']) {
            // Reject DST zones and UTC monthly/yearly date shifts, which cannot
            // be represented by the internal Tokyo civil-date recurrence.
            if (!in_array($original['tz']->getName(),['Asia/Tokyo','Japan','UTC','Etc/UTC','GMT'],true)
                || (in_array($type,['monthly','yearly'],true)&&$original['dt']->format('Y-m-d')!==$event['start_date'])) {
                throw new DomainException('繰り返しタイムゾーンが内部Calendarと等価ではありません。');
            }
        }
        if (isset($rule['UNTIL'])) {
            $u=calendar_import_datetime(['value'=>$rule['UNTIL'],'params'=>[]],$original['tz']);
            if ($u['all_day']!==$original['all_day']) throw new DomainException('UNTILの型が不一致です。');
            $local=$u['all_day']?$u['dt']:$u['dt']->setTimezone(new DateTimeZone('Asia/Tokyo'));
            $until=$local->format('Y-m-d');
            if (!$u['all_day']&&$local->format('H:i:s')<$event['time']['start_time']) $until=$local->modify('-1 day')->format('Y-m-d');
        } elseif ($rule['COUNT']!==null) {
            $remaining=$rule['COUNT']; $cursor=$event['start_date'];
            // Scan bounded one-year windows using the native recurrence engine.
            while ($cursor<='2100-12-31') {
                $end=min(substr($cursor,0,4).'-12-31','2100-12-31');
                $starts=calendar_recurrence_occurrence_starts($type,$event['start_date'],$cursor,$end,null);
                if (count($starts)>=$remaining) { $until=$starts[$remaining-1]; break; }
                $remaining-=count($starts);
                $cursor=(new DateTimeImmutable($end))->modify('+1 day')->format('Y-m-d');
            }
            if ($until===null) throw new DomainException('COUNTが登録可能期間外です。');
        }
    }
    $repeat=calendar_event_recurrence_settings($type,$until,$event['start_date'],$event['end_date']);
    if ($repeat===null) throw new DomainException('繰り返し期間が登録可能範囲外です。');
    return $repeat;
}

function calendar_import_original(array $prop,array $event,array $repeat): string
{
    $dt=calendar_import_datetime($prop,$event['start_original']['tz']);
    if ($dt['all_day']!==$event['time']['all_day']) throw new DomainException('例外の日時型が不一致です。');
    $local=$dt['all_day']?$dt['dt']:$dt['dt']->setTimezone(new DateTimeZone('Asia/Tokyo'));
    $date=$local->format('Y-m-d');
    if (calendar_validate_date($date)===null || (!$dt['all_day']&&$local->format('H:i:s')!==$event['time']['start_time'])
        || !in_array($date,calendar_recurrence_occurrence_starts($repeat['repeat_type'],$event['start_date'],$date,$date,$repeat['repeat_until']),true)) {
        throw new DomainException('元の繰り返しに対応しない例外があります。');
    }
    return $date;
}

function calendar_import_remove_extra(array $node): array
{
    foreach (array_keys($node['props']) as $key) {
        if (str_starts_with($key,'X-RSSREADER-')) unset($node['props'][$key]);
    }
    $node['children']=array_map('calendar_import_remove_extra',$node['children']);
    return $node;
}

function calendar_import_parse(string $raw,bool $extra=true): array
{
    $root=calendar_import_components($raw);
    if (!$extra) $root=calendar_import_remove_extra($root); $groups=[]; $issues=[]; $count=0; $series=[];
    foreach ($root['children'] as $node) {
        if ($node['type']!=='VEVENT') {
            if ($node['type']!=='VTIMEZONE') $issues[]=['uid'=>'','reason'=>$node['type'].' は非対応です。'];
            continue;
        }
        if (++$count>CALENDAR_TRANSFER_MAX_EVENTS) throw new LengthException('VEVENTが2000件を超えています。');
        try {
            $uid=calendar_import_text_value($node['props'],'UID');
            if (!calendar_ics_uid_valid($uid)) throw new DomainException('UIDがありません、または不正です。');
            // Prefix keeps numeric UIDs from being converted to PHP integer keys.
            $groups['u:'.$uid][]=$node;
        } catch (DomainException $e) { $issues[]=['uid'=>'','reason'=>$e->getMessage()]; }
    }
    foreach ($groups as $key=>$nodes) {
        $uid=substr($key,2);
        try {
            $master=null; $overrides=[];
            foreach ($nodes as $node) {
                if (isset($node['props']['RECURRENCE-ID'])) $overrides[]=$node;
                elseif ($master!==null) throw new DomainException('同じUIDの親予定が重複しています。');
                else $master=$node;
            }
            if ($master===null || calendar_import_value($master['props'],'STATUS')==='CANCELLED') throw new DomainException('親予定がない、または取消済みです。');
            $event=calendar_import_event($master); $repeat=calendar_import_repeat($master['props'],$event);
            $exceptions=[];
            foreach ($master['props']['EXDATE']??[] as $prop) {
                foreach (explode(',',$prop['value']) as $value) {
                    $date=calendar_import_original(['value'=>$value,'params'=>$prop['params']],$event,$repeat);
                    $exceptions[$date]=['kind'=>'cancelled'];
                    if (count($exceptions)>CALENDAR_EXCEPTION_MAX_ACTIVE) throw new DomainException('個別例外が500件を超えています。');
                }
            }
            foreach ($overrides as $node) {
                $p=$node['props']; $rid=calendar_import_prop($p,'RECURRENCE-ID');
                $date=calendar_import_original($rid,$event,$repeat);
                if (isset($exceptions[$date]) || isset($p['RRULE'])||isset($p['RDATE'])||isset($p['EXDATE'])) throw new DomainException('例外が重複または非対応です。');
                if (calendar_import_value($p,'STATUS')==='CANCELLED') $exceptions[$date]=['kind'=>'cancelled'];
                else {
                    $override=calendar_import_event($node);
                    if ($override['reminder_present']&&$override['reminder']!==$event['reminder']) throw new DomainException('例外固有のリマインダーは非対応です。');
                    unset($override['start_original']);
                    $exceptions[$date]=['kind'=>'override','event'=>$override];
                }
                if (count($exceptions)>CALENDAR_EXCEPTION_MAX_ACTIVE) throw new DomainException('個別例外が500件を超えています。');
            }
            unset($event['start_original']);
            ksort($exceptions);
            $series[]=['uid'=>$uid,'event'=>$event,'repeat'=>$repeat,'exceptions'=>$exceptions];
        } catch (DomainException $e) { $issues[]=['uid'=>$uid,'reason'=>$e->getMessage()]; }
    }
    $p=$root['props'];
    $name=calendar_import_text_value($p,'X-RSSREADER-CALENDAR-NAME',calendar_import_text_value($p,'NAME',calendar_import_text_value($p,'X-WR-CALNAME','Imported Calendar')));
    $color=calendar_import_value($p,'X-RSSREADER-CALENDAR-COLOR')??strtolower(calendar_import_value($p,'COLOR')??'blue');
    $marks=[];
    if (count($p['X-RSSREADER-DAY-MARK']??[])>CALENDAR_IMPORT_MAX_MARKS) throw new LengthException('日付マークが多すぎます。');
    foreach ($p['X-RSSREADER-DAY-MARK']??[] as $prop) {
        try {
            $mark=json_decode(calendar_import_text($prop['value']),true,8,JSON_THROW_ON_ERROR);
            $date=calendar_validate_date($mark['date']??null);
            $label=app_validate_text($mark['label']??null,12,false); $note=app_validate_text($mark['note']??null,120);
            if ($date===null||$label===null||$note===null||isset($marks[$date])) throw new DomainException('日付マークが不正または重複しています。');
            $marks[$date]=['label'=>$label,'note'=>$note];
        } catch (JsonException|DomainException $e) { $issues[]=['uid'=>'','reason'=>'日付マークが不正または重複しています。']; }
    }
    return ['series'=>$series,'issues'=>$issues,'name'=>$name,'color'=>$color,'marks'=>$marks,'components'=>$count];
}
