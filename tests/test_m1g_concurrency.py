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
    print('SKIP: PHP CLI unavailable for M1-G process concurrency test.')
    raise SystemExit(0)

with tempfile.TemporaryDirectory(prefix='rss-m1g-concurrency-') as td:
    tmp = Path(td)
    cache_dir = tmp / 'cache'
    cache_dir.mkdir()
    counter = tmp / 'transport-count.txt'
    barrier = tmp / 'start.signal'
    worker = tmp / 'worker.php'
    source_url = 'https://concurrent.example.test/feed.xml'
    key = hashlib.sha256(source_url.encode()).hexdigest()
    cache_path = cache_dir / f'feed-v1-{key}.json'
    state_path = cache_dir / f'feed-v1-{key}.state.json'
    body = b'VALID:stale-concurrent'
    old = int(time.time()) - 120
    cache_path.write_text(json.dumps({
        'schema': 2,
        'source_url': source_url,
        'effective_url': source_url,
        'status': 200,
        'fetched_at': old,
        'body_fetched_at': old,
        'validated_at': old,
        'etag': None,
        'last_modified': None,
        'body_base64': base64.b64encode(body).decode(),
        'body_sha256': hashlib.sha256(body).hexdigest(),
    }), encoding='utf-8')

    root_literal = json.dumps(str(ROOT))
    worker.write_text(f'''<?php
    declare(strict_types=1);
    $root = {root_literal};
    require_once $root . '/app/feed/feed_fetch_service.php';

    final class M1gConcurrentTransport implements FeedTransportInterface {{
        public function __construct(private string $counterPath) {{}}
        public function fetch(FeedSource $source, array $validators = []): array {{
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
                'ok'=>false, 'url'=>$source->url, 'status'=>503, 'body'=>'',
                'etag'=>null, 'last_modified'=>null, 'not_modified'=>false,
                'retry_after'=>'300', 'error_code'=>'http_status', 'error_message'=>'synthetic 503'
            ];
        }}
    }}
    class M1gConcurrentParser extends FeedParser {{
        public function parse_start(mixed $contents, ?string $sourceUrl = null): array {{
            if ($contents !== 'VALID:stale-concurrent') {{ $this->last_error='invalid'; return []; }}
            return ['type'=>'rss2','channel'=>['title'=>'Stale','link'=>$sourceUrl,'description'=>''], 'item'=>[]];
        }}
    }}
    $cacheDir = $argv[1]; $counter = $argv[2]; $barrier = $argv[3];
    $deadline = microtime(true) + 5.0;
    while (!is_file($barrier) && microtime(true) < $deadline) {{ usleep(10000); }}
    $source = FeedSource::fromValidatedValues(1, 1, '{source_url}');
    $cache = new FeedCache($cacheDir, 60, 4096);
    $service = new FeedFetchService(
        new M1gConcurrentTransport($counter), new M1gConcurrentParser(), $cache,
        true, 3000, false, true, 3600, true, 86400
    );
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
        check(process.returncode == 0, f'resilience worker exits successfully ({err.strip() or "no stderr"})')
        outputs.append(json.loads(out))

    count = int(counter.read_text(encoding='utf-8').strip())
    check(count == 1, 'five simultaneous stale requests perform one failing upstream request')
    check(all(item['ok'] is True for item in outputs), 'all simultaneous requests receive bounded stale Feed data')
    check(all(item['cache_status'] == 'stale' for item in outputs), 'all simultaneous requests identify the internal stale-cache path')
    state = json.loads(state_path.read_text(encoding='utf-8'))
    check(state['last_result'] == 'transient_error', 'concurrent failure stores one transient state')
    check(state['consecutive_failures'] == 1, 'waiting processes do not increment the same failure repeatedly')
    check(state['last_http_status'] == 503 and state['last_error_code'] == 'http_status', 'concurrent state stores only bounded status metadata')
    check(state['next_retry_at'] >= state['last_attempt_at'] + 300, 'Retry-After controls the concurrent retry time')
    check(source_url not in state_path.read_text(encoding='utf-8'), 'concurrent state file does not expose the Feed URL')
    leftovers = [p for p in cache_dir.glob('.feed-state-*') if p.is_file()]
    check(leftovers == [], 'concurrent state writes leave no temporary files')

print('All M1-G process concurrency checks passed.')
