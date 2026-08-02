from __future__ import annotations
from pathlib import Path
import base64
import hashlib
import json
import shutil
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]


def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)


php = shutil.which('php')
if php is None:
    print('SKIP: PHP CLI unavailable for M1-F process concurrency test.')
    raise SystemExit(0)

with tempfile.TemporaryDirectory(prefix='rss-m1f-concurrency-') as td:
    tmp = Path(td)
    cache_dir = tmp / 'cache'
    cache_dir.mkdir()
    counter = tmp / 'transport-count.txt'
    barrier = tmp / 'start.signal'
    worker = tmp / 'worker.php'
    source_url = 'https://concurrent.example.test/feed.xml'
    key = hashlib.sha256(source_url.encode()).hexdigest()
    cache_path = cache_dir / f'feed-v1-{key}.json'
    body = b'VALID:concurrent-304'
    old = int(time.time()) - 120
    cache_path.write_text(json.dumps({
        'schema': 2,
        'source_url': source_url,
        'effective_url': source_url,
        'status': 200,
        'fetched_at': old,
        'body_fetched_at': old,
        'validated_at': old,
        'etag': '"concurrent-v1"',
        'last_modified': 'Sat, 01 Aug 2026 06:00:00 GMT',
        'body_base64': base64.b64encode(body).decode(),
        'body_sha256': hashlib.sha256(body).hexdigest(),
    }), encoding='utf-8')

    root_literal = json.dumps(str(ROOT))
    worker.write_text(f'''<?php
    declare(strict_types=1);
    $root = {root_literal};
    require_once $root . '/app/feed/feed_fetch_service.php';

    final class ConditionalConcurrentTransport implements FeedTransportInterface {{
        public function __construct(private string $counterPath) {{}}
        public function fetch(FeedSource $source, array $validators = []): array {{
            if (($validators['etag'] ?? '') !== '"concurrent-v1"') {{
                return ['ok'=>false, 'url'=>$source->url, 'status'=>0, 'body'=>'', 'error_code'=>'missing_validator'];
            }}
            $h = fopen($this->counterPath, 'c+b');
            flock($h, LOCK_EX);
            rewind($h);
            $raw = stream_get_contents($h);
            $count = (int) trim(is_string($raw) ? $raw : '0');
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, (string) ($count + 1));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);
            usleep(350000);
            return [
                'ok'=>true, 'url'=>$source->url, 'status'=>304, 'body'=>'',
                'etag'=>'"concurrent-v1"', 'last_modified'=>null, 'not_modified'=>true
            ];
        }}
    }}
    class ConditionalConcurrentParser extends FeedParser {{
        public function parse_start(mixed $contents, ?string $sourceUrl = null, bool $includeIdentity = false): array {{
            if ($contents !== 'VALID:concurrent-304') {{ $this->last_error='invalid'; return []; }}
            return ['type'=>'rss2','channel'=>['title'=>'Concurrent','link'=>$sourceUrl,'description'=>''], 'item'=>[]];
        }}
    }}
    $cacheDir = $argv[1]; $counter = $argv[2]; $barrier = $argv[3];
    $deadline = microtime(true) + 5.0;
    while (!is_file($barrier) && microtime(true) < $deadline) {{ usleep(10000); }}
    $source = FeedSource::fromValidatedValues(1, 1, '{source_url}');
    $cache = new FeedCache($cacheDir, 60, 4096);
    $service = new FeedFetchService(new ConditionalConcurrentTransport($counter), new ConditionalConcurrentParser(), $cache, true, 3000, true);
    $result = $service->load($source);
    echo json_encode(['ok'=>$result['ok'] ?? false, 'cache_status'=>$result['cache_status'] ?? '']);
    ''', encoding='utf-8')

    processes = [
        subprocess.Popen(
            [php, str(worker), str(cache_dir), str(counter), str(barrier)],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True
        )
        for _ in range(5)
    ]
    time.sleep(0.15)
    barrier.write_text('go', encoding='utf-8')
    outputs = []
    for process in processes:
        out, err = process.communicate(timeout=10)
        check(process.returncode == 0, f'conditional worker exits successfully ({err.strip() or "no stderr"})')
        outputs.append(json.loads(out))

    count = int(counter.read_text(encoding='utf-8').strip())
    statuses = sorted(item['cache_status'] for item in outputs)
    check(count == 1, 'five stale-cache requests perform exactly one conditional HTTP request')
    check(all(item['ok'] is True for item in outputs), 'all simultaneous HTTP 304 requests receive a successful Feed result')
    check(statuses.count('revalidated') == 1 and statuses.count('hit') == 4, 'one request revalidates and four waiting requests use the refreshed cache')
    payload = json.loads(cache_path.read_text(encoding='utf-8'))
    check(payload['body_fetched_at'] == old, 'HTTP 304 concurrency keeps the original body fetch time')
    check(payload['validated_at'] > old, 'HTTP 304 concurrency updates the validation time')
    leftovers = [p for p in cache_dir.glob('.feed-cache-*') if p.is_file()]
    check(leftovers == [], 'conditional cache writes leave no temporary files')

print('All M1-F process concurrency checks passed.')
