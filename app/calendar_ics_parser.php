<?php

declare(strict_types=1);

/**
 * ICS/iCalendar reader for read-only Calendar integration.
 *
 * Keep this separate from editable local calendar_event tables. All results are
 * bounded, user-owned by the caller and escaped at the presentation boundary.
 * RFC 5545 subset: DTSTART/DTEND/DURATION, UID, SUMMARY/DESCRIPTION/LOCATION,
 * URL, RRULE (DAILY/WEEKLY/MONTHLY/YEARLY + common BY*), RDATE, EXDATE and
 * RECURRENCE-ID overrides/cancellations. Unsupported complex rules are skipped,
 * rather than silently inventing incorrect recurring dates.
 */
const CALENDAR_ICS_PARSE_MAX_LINES = 16000;
const CALENDAR_ICS_PARSE_MAX_VEVENTS = 600;
const CALENDAR_ICS_PARSE_MAX_OCCURRENCES = 1200;
const CALENDAR_ICS_PARSE_MAX_PERIODS = 40000;

function calendar_ics_unescape_text(string $value): string
{
    return str_ireplace(['\\n', '\\N', '\\,', '\\;', '\\\\'], ["\n", "\n", ',', ';', '\\'], $value);
}

/** @return list<array<string,mixed>> */
function calendar_ics_component_events(string $ics): array
{
    if (strlen($ics) > 1048576 || !preg_match('/\ABEGIN:VCALENDAR\r?\n/i', $ics)) {
        return [];
    }
    $text = str_replace(["\r\n", "\r"], "\n", $ics);
    $text = preg_replace("/\n[ \t]/", '', $text) ?? '';
    $lines = explode("\n", $text);
    if (count($lines) > CALENDAR_ICS_PARSE_MAX_LINES) {
        return [];
    }
    $events = [];
    $active = null;
    foreach ($lines as $line) {
        if (strlen($line) > 8192) {
            return [];
        }
        $line = rtrim($line, "\n");
        if (strtoupper($line) === 'BEGIN:VEVENT') {
            $active = [];
            continue;
        }
        if (strtoupper($line) === 'END:VEVENT') {
            if ($active !== null) {
                $events[] = $active;
                if (count($events) > CALENDAR_ICS_PARSE_MAX_VEVENTS) {
                    return [];
                }
            }
            $active = null;
            continue;
        }
        if ($active === null) {
            continue;
        }
        $colon = strpos($line, ':');
        if ($colon === false) {
            continue;
        }
        $head = substr($line, 0, $colon);
        $value = substr($line, $colon + 1);
        $attrs = explode(';', $head);
        $name = strtoupper(array_shift($attrs));
        if (!preg_match('/\A[A-Z0-9-]{1,40}\z/', $name)) {
            continue;
        }
        $params = [];
        foreach ($attrs as $attr) {
            $pair = explode('=', $attr, 2);
            if (count($pair) === 2) {
                $params[strtoupper(trim($pair[0]))] = trim(trim($pair[1]), '"');
            }
        }
        $active[$name][] = ['value' => $value, 'params' => $params];
    }
    return $events;
}

/** @return array{value:string,params:array<string,string>}|null */
function calendar_ics_property(array $event, string $key): ?array
{
    $value = $event[$key][0] ?? null;
    return is_array($value) ? $value : null;
}

function calendar_ics_timezone(string $id, DateTimeZone $fallback): ?DateTimeZone
{
    if ($id === '') {
        return $fallback;
    }
    $id = trim($id, '"');
    $windows = [
        'Tokyo Standard Time' => 'Asia/Tokyo',
        'UTC' => 'UTC',
        'GMT Standard Time' => 'Europe/London',
        'Eastern Standard Time' => 'America/New_York',
        'Central Standard Time' => 'America/Chicago',
        'Mountain Standard Time' => 'America/Denver',
        'Pacific Standard Time' => 'America/Los_Angeles',
        'W. Europe Standard Time' => 'Europe/Berlin',
        'China Standard Time' => 'Asia/Shanghai',
        'Korea Standard Time' => 'Asia/Seoul',
        'AUS Eastern Standard Time' => 'Australia/Sydney',
    ];
    $id = $windows[$id] ?? $id;
    try {
        return new DateTimeZone($id);
    } catch (Throwable) {
        return null;
    }
}

/**
 * @return array{dt:DateTimeImmutable,all_day:bool,tz:DateTimeZone}|null
 */
function calendar_ics_datetime(array $prop, DateTimeZone $fallback): ?array
{
    $value = trim($prop['value']);
    $params = $prop['params'] ?? [];
    if (preg_match('/\A\d{8}\z/', $value)) {
        $tz = calendar_ics_timezone((string) ($params['TZID'] ?? ''), $fallback);
        if (!$tz) return null;
        $dt = DateTimeImmutable::createFromFormat('!Ymd', $value, $tz);
        return $dt instanceof DateTimeImmutable && $dt->format('Ymd') === $value
            ? ['dt' => $dt, 'all_day' => true, 'tz' => $tz] : null;
    }
    $utc = str_ends_with($value, 'Z');
    if (!preg_match('/\A\d{8}T\d{6}Z?\z/', $value)) {
        return null;
    }
    $tz = $utc ? new DateTimeZone('UTC')
        : calendar_ics_timezone((string) ($params['TZID'] ?? ''), $fallback);
    if (!$tz) return null;
    $bare = $utc ? substr($value, 0, -1) : $value;
    $dt = DateTimeImmutable::createFromFormat('!Ymd\THis', $bare, $tz);
    return $dt instanceof DateTimeImmutable && $dt->format('Ymd\THis') === $bare
        ? ['dt' => $dt, 'all_day' => false, 'tz' => $tz] : null;
}

/** @return array{start:DateTimeImmutable,end:DateTimeImmutable,all_day:bool,tz:DateTimeZone}|null */
function calendar_ics_times(array $props, DateTimeZone $fallback): ?array
{
    $startProp = calendar_ics_property($props, 'DTSTART');
    if ($startProp === null) return null;
    $start = calendar_ics_datetime($startProp, $fallback);
    if ($start === null) return null;
    $endProp = calendar_ics_property($props, 'DTEND');
    $endValue = $endProp === null ? null : calendar_ics_datetime($endProp, $start['tz']);
    if ($endProp !== null && ($endValue === null || $endValue['all_day'] !== $start['all_day'])) {
        return null;
    }
    if ($endValue !== null) {
        $end = $endValue['dt'];
    } else {
        $duration = calendar_ics_property($props, 'DURATION');
        $durationValue = $duration['value'] ?? '';
        if ($durationValue !== '' && preg_match('/\AP(?:\d+W|(?:(?:\d+D)?(?:T(?:(?:\d+H)?(?:\d+M)?(?:\d+S)?)?)))\z/', $durationValue) === 1) {
            try {
                $end = $start['dt']->add(new DateInterval($durationValue));
            } catch (Throwable) {
                return null;
            }
        } else {
            $end = $start['all_day'] ? $start['dt']->modify('+1 day') : $start['dt'];
        }
    }
    if ($end < $start['dt']) return null;
    // RFC 5545: all-day DTEND is exclusive, and must be strictly later.
    if ($start['all_day'] && $end <= $start['dt']) return null;
    return ['start' => $start['dt'], 'end' => $end, 'all_day' => $start['all_day'], 'tz' => $start['tz']];
}

/** @return list<int> timestamps */
function calendar_ics_date_list(array $props, string $name, DateTimeZone $tz): array
{
    $result = [];
    foreach (($props[$name] ?? []) as $prop) {
        foreach (explode(',', (string) $prop['value']) as $value) {
            $parsed = calendar_ics_datetime(['value'=>$value,'params'=>$prop['params']], $tz);
            if ($parsed !== null) {
                $result[] = $parsed['dt']->getTimestamp();
            }
        }
    }
    return $result;
}

/** @return array<string,mixed>|null */
function calendar_ics_rrule(array $props): ?array
{
    $p = calendar_ics_property($props, 'RRULE');
    if ($p === null) return null;
    $rule = [];
    foreach (explode(';', strtoupper(trim($p['value']))) as $piece) {
        $pair = explode('=', $piece, 2);
        if (count($pair) !== 2 || isset($rule[$pair[0]])) return ['invalid' => true];
        $rule[$pair[0]] = $pair[1];
    }
    $freq = $rule['FREQ'] ?? '';
    if (!in_array($freq, ['DAILY','WEEKLY','MONTHLY','YEARLY'], true)) return ['invalid'=>true];
    $allowed = ['FREQ','INTERVAL','COUNT','UNTIL','BYDAY','BYMONTHDAY','BYMONTH','BYSETPOS','WKST'];
    foreach (array_keys($rule) as $key) {
        if (!in_array($key, $allowed, true)) return ['invalid'=>true];
    }
    $interval = (int) ($rule['INTERVAL'] ?? '1');
    if ($interval < 1 || $interval > 366 || (isset($rule['COUNT']) && (!ctype_digit($rule['COUNT']) || (int)$rule['COUNT'] < 1 || (int)$rule['COUNT'] > 40000))) {
        return ['invalid'=>true];
    }
    foreach (['BYMONTH','BYMONTHDAY','BYSETPOS'] as $part) {
        if (!isset($rule[$part])) continue;
        $bounds = ['BYMONTH'=>[1,12], 'BYMONTHDAY'=>[-31,31], 'BYSETPOS'=>[-366,366]][$part];
        foreach (explode(',', $rule[$part]) as $value) {
            if (!preg_match('/\A[+-]?\d{1,3}\z/', $value) || (int)$value === 0
                || (int)$value < $bounds[0] || (int)$value > $bounds[1]) {
                return ['invalid'=>true];
            }
        }
    }
    foreach (explode(',', $rule['BYDAY'] ?? '') as $byday) {
        if ($byday !== '' && !preg_match('/\A(?:[+-]?[1-5])?(?:MO|TU|WE|TH|FR|SA|SU)\z/', $byday)) {
            return ['invalid'=>true];
        }
    }
    if (isset($rule['BYSETPOS']) && !isset($rule['BYDAY']) && !isset($rule['BYMONTHDAY'])) return ['invalid'=>true];
    if (isset($rule['BYDAY']) && preg_match('/[0-9]/', $rule['BYDAY'])
        && !in_array($freq, ['MONTHLY','YEARLY'], true)) return ['invalid'=>true];
    if ($freq === 'YEARLY' && isset($rule['BYDAY']) && preg_match('/[0-9]/', $rule['BYDAY']) && !isset($rule['BYMONTH'])) {
        return ['invalid'=>true]; // Year-wide ordinal BYDAY requires a different grouping.
    }
    $rule['INTERVAL'] = $interval;
    $rule['COUNT'] = isset($rule['COUNT']) ? (int) $rule['COUNT'] : null;
    return $rule;
}

function calendar_ics_day_matches(DateTimeImmutable $day, DateTimeImmutable $first, array $rule): bool
{
    $month = (int) $day->format('n');
    $dayNum = (int) $day->format('j');
    $lastDay = (int) $day->format('t');
    $freq = $rule['FREQ'];
    if (isset($rule['BYMONTH']) && !in_array($month, array_map('intval', explode(',', $rule['BYMONTH'])), true)) return false;
    if (isset($rule['BYMONTHDAY'])) {
        $days = array_map('intval', explode(',', $rule['BYMONTHDAY']));
        if (!in_array($dayNum, $days, true) && !in_array($dayNum-$lastDay-1, $days, true)) return false;
    }
    if (isset($rule['BYDAY'])) {
        $weekday = ['SU','MO','TU','WE','TH','FR','SA'][(int)$day->format('w')];
        $matched = false;
        foreach (explode(',', $rule['BYDAY']) as $code) {
            if (substr($code,-2) !== $weekday) continue;
            $nth = substr($code,0,-2);
            if ($nth === '') { $matched = true; break; }
            $ordinal = (int)$nth;
            $fromStart = 1 + intdiv($dayNum-1, 7);
            $fromEnd = -1 - intdiv($lastDay-$dayNum, 7);
            if ($ordinal === $fromStart || $ordinal === $fromEnd) { $matched = true; break; }
        }
        if (!$matched) return false;
    } else if ($freq === 'WEEKLY' && !isset($rule['BYMONTHDAY'])) {
        if ($day->format('w') !== $first->format('w')) return false;
    }
    if ($freq === 'MONTHLY' && !isset($rule['BYDAY']) && !isset($rule['BYMONTHDAY'])) {
        if ($dayNum !== (int)$first->format('j')) return false;
    }
    if ($freq === 'YEARLY' && !isset($rule['BYDAY']) && !isset($rule['BYMONTHDAY'])) {
        if ($dayNum !== (int)$first->format('j')) return false;
        if (!isset($rule['BYMONTH']) && $month !== (int)$first->format('n')) return false;
    }
    return true;
}

/**
 * Expand one RRULE into UTC timestamp keys, keeping the source timezone's local
 * clock (DST-safe). Bounded to <= 42 visible days and <= 40k periods per event.
 * @return list<int>
 */
function calendar_ics_rule_instances(array $event, array $rule, string $rangeStart, string $rangeEnd): array
{
    if (isset($rule['invalid'])) return [$event['start']->getTimestamp()];
    $first = $event['start'];
    $tz = $event['tz'];
    $freq = $rule['FREQ'];
    $interval = $rule['INTERVAL'];
    $wkst = $rule['WKST'] ?? 'MO';
    if (!in_array($wkst, ['MO','TU','WE','TH','FR','SA','SU'], true)) return [];
    $wkstIndex = array_search($wkst, ['SU','MO','TU','WE','TH','FR','SA'], true);
    $beginDay = $first->setTime(0,0,0);
    $basePeriod = match ($freq) {
        'DAILY' => $beginDay,
        'WEEKLY' => $beginDay->modify('-'.(((int)$beginDay->format('w')-$wkstIndex+7)%7).' days'),
        'MONTHLY' => $beginDay->modify('first day of this month'),
        'YEARLY' => $beginDay->setDate((int)$beginDay->format('Y'),1,1),
    };
    $periodLength = ['DAILY'=>'days','WEEKLY'=>'weeks','MONTHLY'=>'months','YEARLY'=>'years'][$freq];
    $window = new DateTimeImmutable($rangeStart . ' 00:00:00', $tz);
    $until = $rule['UNTIL'] ?? null;
    $untilLimit = null;
    if ($until !== null) {
        $u = calendar_ics_datetime(['value'=>$until,'params'=>[]], $tz);
        if (!$u) return [$first->getTimestamp()];
        $untilLimit = $u['all_day'] ? $u['dt']->setTime(23,59,59)->getTimestamp() : $u['dt']->getTimestamp();
    }
    // With COUNT, every previous occurrence contributes; otherwise skip old years.
    $offset = 0;
    if ($rule['COUNT'] === null) {
        $gap = $basePeriod->diff($window);
        $days = (int) $gap->format('%r%a');
        if ($days > 0) {
            $unit = match($freq) {
                'DAILY' => $days,
                'WEEKLY' => (int)floor($days/7),
                'MONTHLY' => max(0, ((int)$window->format('Y')-(int)$basePeriod->format('Y'))*12
                    + ((int)$window->format('n')-(int)$basePeriod->format('n'))),
                'YEARLY' => max(0,(int)$window->format('Y')-(int)$basePeriod->format('Y')),
            };
            // Preserve some overlap for long-duration events and DST transitions.
            $durationDays = min(366, (int) ceil(($event['end']->getTimestamp()-$first->getTimestamp())/86400));
            $offset = max(0, intdiv(max(0,$unit-$durationDays-3),$interval));
        }
    }
    $endBound = new DateTimeImmutable($rangeEnd.' 23:59:59',$tz);
    $result = [];
    $count = 0;
    for ($step=0; $step<CALENDAR_ICS_PARSE_MAX_PERIODS; $step++) {
        $index = ($offset+$step)*$interval;
        $start = $basePeriod->modify('+' . $index . ' ' . $periodLength);
        if ($start > $endBound) break;
        if ($untilLimit !== null && $start->getTimestamp() > $untilLimit) break;
        $periodEnd = $start->modify('+1 ' . $periodLength);
        $dates = [];
        for ($day=$start; $day<$periodEnd; $day=$day->modify('+1 day')) {
            if ($day < $beginDay) continue;
            if (calendar_ics_day_matches($day, $first, $rule)) $dates[]=$day;
        }
        if (isset($rule['BYSETPOS'])) {
            $picked = [];
            foreach (explode(',',$rule['BYSETPOS']) as $pos) {
                $i=(int)$pos;
                $key=$i>0?$i-1:count($dates)+$i;
                if (isset($dates[$key])) $picked[$key]=$dates[$key];
            }
            ksort($picked);
            $dates = array_values($picked);
        }
        foreach ($dates as $date) {
            $occur = $date->setTime((int)$first->format('H'),(int)$first->format('i'),(int)$first->format('s'));
            if ($occur < $first) continue;
            if ($untilLimit !== null && $occur->getTimestamp() > $untilLimit) continue;
            $count++;
            if ($rule['COUNT'] !== null && $count > $rule['COUNT']) break 2;
            if ($occur <= $endBound) $result[]=$occur->getTimestamp();
            if (count($result)>CALENDAR_ICS_PARSE_MAX_OCCURRENCES) return array_slice($result,0,CALENDAR_ICS_PARSE_MAX_OCCURRENCES);
        }
    }
    return $result;
}

/** @return array<string,mixed>|null */
function calendar_ics_public_event(
    int $subscriptionId, string $name, string $color, string $uidHash,
    array $event, int $startTimestamp, string $rangeStart, string $rangeEnd,
    string $recurrenceKey
): ?array {
    $seconds=max(0,$event['end']->getTimestamp()-$event['start']->getTimestamp());
    $tz=$event['tz'];
    $allDay=$event['all_day'];
    $localStart=(new DateTimeImmutable('@'.$startTimestamp))->setTimezone(
        $allDay ? $tz : new DateTimeZone('Asia/Tokyo')
    );
    $localEnd=(new DateTimeImmutable('@'.($startTimestamp+$seconds)))->setTimezone(
        $allDay ? $tz : new DateTimeZone('Asia/Tokyo')
    );
    // DATE values represent whole civil dates regardless of the user's display TZ.
    $startDate=$localStart->format('Y-m-d');
    $daySpan=max(1,(int)$event['start']->diff($event['end'])->days);
    $endDate=$allDay ? $localStart->modify('+'.($daySpan-1).' days')->format('Y-m-d')
        : $localEnd->format('Y-m-d');
    if ($startDate>$rangeEnd || $endDate<$rangeStart) return null;
    $props=$event['props'];
    $title=calendar_ics_unescape_text((string)(calendar_ics_property($props,'SUMMARY')['value'] ?? '（無題）'));
    $title=mb_substr(trim($title),0,200);
    if ($title==='') $title='（無題）';
    $note=mb_substr(calendar_ics_unescape_text((string)(calendar_ics_property($props,'DESCRIPTION')['value'] ?? '')),0,3000);
    $location=mb_substr(calendar_ics_unescape_text((string)(calendar_ics_property($props,'LOCATION')['value'] ?? '')),0,300);
    $url=(string)(calendar_ics_property($props,'URL')['value'] ?? '');
    $validUrl=filter_var($url,FILTER_VALIDATE_URL) && preg_match('/\Ahttps:\/\//i',$url) && strlen($url)<=2048;
    return [
        'kind'=>'event', 'external_read_only'=>true, 'source_type'=>'ics',
        'event_id'=>'ics:'.$subscriptionId.':'.$uidHash.':'.substr(hash('sha256',$recurrenceKey),0,14),
        'occurrence_key'=>'ics:'.$subscriptionId.':'.$uidHash.':'.substr(hash('sha256',$recurrenceKey),0,14),
        'title'=>$title,'note'=>$note,'location'=>$location,'url'=>$validUrl?$url:null,
        'color'=>$color,'calendar_source_id'=>'ics:'.$subscriptionId,
        'calendar_source_name'=>$name,'calendar_source_color'=>$color,
        'occurrence_start_date'=>$startDate,'occurrence_end_date'=>$endDate,
        'start_date'=>$startDate,'end_date'=>$endDate,'all_day'=>$allDay,
        'start_time'=>$allDay?null:$localStart->format('H:i'),
        'end_time'=>$allDay?null:$localEnd->format('H:i'),
        'repeat_type'=>'none','reminder'=>'none',
    ];
}

/**
 * Read a complete raw ICS feed for a requested [start,end] date window.
 * No writes or network I/O. Custom/unsupported recurrence data is not edited.
 * @return list<array<string,mixed>>
 */
function calendar_ics_expand(string $raw, int $subscriptionId, string $name, string $color, string $rangeStart, string $rangeEnd): array
{
    if ($subscriptionId<1 || $rangeEnd<$rangeStart) return [];
    $default=new DateTimeZone('Asia/Tokyo');
    $groups=[];
    foreach (calendar_ics_component_events($raw) as $props) {
        $revisionProp=calendar_ics_property($props,'RECURRENCE-ID');
        $cancelled=strtoupper((string)(calendar_ics_property($props,'STATUS')['value'] ?? ''))==='CANCELLED';
        $timing=calendar_ics_times($props,$default);
        // Cancelled exceptions may legitimately omit DTSTART/DTEND.
        if (!$timing && $cancelled && $revisionProp) {
            $rid=calendar_ics_datetime($revisionProp,$default);
            if ($rid) {
                $timing=['start'=>$rid['dt'],'end'=>$rid['dt'],
                    'all_day'=>$rid['all_day'],'tz'=>$rid['tz']];
            }
        }
        if (!$timing) continue;
        $uid=(string)(calendar_ics_property($props,'UID')['value'] ?? '');
        if ($uid==='') $uid=hash('sha256',serialize($props));
        $hash=substr(hash('sha256',$uid),0,20);
        $revisionProp=calendar_ics_property($props,'RECURRENCE-ID');
        $revision=$revisionProp ? calendar_ics_datetime($revisionProp,$timing['tz']) : null;
        $groupKey=$hash;
        if (!isset($groups[$groupKey])) $groups[$groupKey]=['master'=>null,'exceptions'=>[]];
        $record=$timing+['props'=>$props];
        if ($revision!==null) {
            $groups[$groupKey]['exceptions'][$revision['dt']->getTimestamp()]=$record;
        } else {
            $groups[$groupKey]['master']=$record;
        }
    }
    $events=[];
    foreach ($groups as $hash=>$group) {
        $master=$group['master'];
        $exceptions=$group['exceptions'];
        $skip=[];
        foreach ($exceptions as $rid=>$override) {
            $skip[(int)$rid]=true;
            $status=strtoupper((string)(calendar_ics_property($override['props'],'STATUS')['value']??''));
            if ($status==='CANCELLED') continue;
            $item=calendar_ics_public_event($subscriptionId,$name,$color,(string)$hash,
                $override,$override['start']->getTimestamp(),$rangeStart,$rangeEnd,(string)$rid);
            if ($item) $events[]=$item;
        }
        if (!$master) continue;
        $status=strtoupper((string)(calendar_ics_property($master['props'],'STATUS')['value']??''));
        if ($status==='CANCELLED') continue;
        $rule=calendar_ics_rrule($master['props']);
        $starts=$rule?calendar_ics_rule_instances($master,$rule,$rangeStart,$rangeEnd):[$master['start']->getTimestamp()];
        foreach (calendar_ics_date_list($master['props'],'RDATE',$master['tz']) as $timestamp) $starts[]=$timestamp;
        foreach (calendar_ics_date_list($master['props'],'EXDATE',$master['tz']) as $timestamp) $skip[$timestamp]=true;
        foreach (array_unique($starts) as $timestamp) {
            if (isset($skip[$timestamp])) continue;
            $item=calendar_ics_public_event($subscriptionId,$name,$color,(string)$hash,
                $master,(int)$timestamp,$rangeStart,$rangeEnd,(string)$timestamp);
            if ($item) $events[]=$item;
            if (count($events)>=CALENDAR_ICS_PARSE_MAX_OCCURRENCES) break 2;
        }
    }
    usort($events,static fn($a,$b)=>[$a['occurrence_start_date'],$a['start_time']??'',$a['event_id']]
        <=>[$b['occurrence_start_date'],$b['start_time']??'',$b['event_id']]);
    return $events;
}

/** Integrates separately-cached ICS feeds into Calendar's existing read-only range. */
function calendar_ics_range(int $ownerId,string $rangeStart,string $rangeEnd): array
{
    $result=['sources'=>[],'events'=>[]];
    if ($ownerId<1 || !calendar_ics_schema_ready(conn_db())) return $result;
    $stmt=conn_db()->prepare(
        'SELECT ics_id, ics_name, ics_color, ics_raw FROM '.db_table_identifier('calendar_ics_subscription')
        .' WHERE ics_owner = :owner ORDER BY ics_id ASC LIMIT '.CALENDAR_ICS_MAX_ACTIVE
    );
    $stmt->execute([':owner'=>$ownerId]);
    foreach ($stmt->fetchAll() as $row) {
        $id=(int)$row['ics_id']; if ($id<1) continue;
        $name=(string)$row['ics_name'];
        $color=in_array((string)$row['ics_color'],['blue','green','purple','yellow','red'],true)
            ?(string)$row['ics_color']:'blue';
        $result['sources'][]=['source_id'=>'ics:'.$id,'name'=>$name,'color'=>$color,
            'source_type'=>'ics','is_default'=>false,'read_only'=>true];
        $raw=$row['ics_raw'];
        if (!is_string($raw) || $raw==='') continue;
        $items=calendar_ics_expand($raw,$id,$name,$color,$rangeStart,$rangeEnd);
        foreach ($items as $item) {
            if (count($result['events'])>=CALENDAR_ICS_PARSE_MAX_OCCURRENCES) break 2;
            $result['events'][]=$item;
        }
    }
    return $result;
}
