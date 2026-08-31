<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/remote_file/remote_exception.php';
require_once __DIR__ . '/../app/remote_file/remote_path.php';
require_once __DIR__ . '/../app/remote_file/remote_provider.php';
require_once __DIR__ . '/../app/remote_file/remote_service.php';

final class V129PathProvider implements RemoteFileProvider
{
    /** @param array<string,list<array{name:string,path:string,type:string,size:?int,modified_at:?string}>> $listings */
    public function __construct(private array $listings)
    {
    }

    public function testConnection(): array { return ['connected' => true, 'code' => 'connected']; }
    public function list(string $relativePath): array { return $this->listings[$relativePath] ?? []; }
    public function download(string $relativePath, $outputStream, int $maxBytes): void {}
    public function upload($inputStream, int $size, string $relativePath, bool $overwrite): void {}
    public function mkdir(string $relativePath): void {}
    public function move(string $fromRelativePath, string $toRelativePath, bool $overwrite): void {}
    public function delete(string $relativePath, bool $directory): void {}
}

$provider = new V129PathProvider([
    '/' => [
        ['name'=>'safe','path'=>'/safe','type'=>'directory','size'=>null,'modified_at'=>null],
        ['name'=>'link','path'=>'/link','type'=>'symlink','size'=>null,'modified_at'=>null],
        ['name'=>'unknown','path'=>'/unknown','type'=>'other','size'=>null,'modified_at'=>null],
    ],
    '/safe' => [
        ['name'=>'child','path'=>'/safe/child','type'=>'directory','size'=>null,'modified_at'=>null],
        ['name'=>'file.txt','path'=>'/safe/file.txt','type'=>'file','size'=>12,'modified_at'=>null],
        ['name'=>'link.txt','path'=>'/safe/link.txt','type'=>'symlink','size'=>null,'modified_at'=>null],
    ],
    '/safe/child' => [
        ['name'=>'deep.txt','path'=>'/safe/child/deep.txt','type'=>'file','size'=>5,'modified_at'=>null],
    ],
]);

$pass = 0;
$fail = 0;
function v129_path_check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$label}\n";
    } else {
        $fail++;
        echo "FAIL: {$label}\n";
    }
}

try {
    remote_service_assert_safe_path($provider, '/safe/child/deep.txt');
    v129_path_check(true, 'safe nested file path is accepted');
} catch (Throwable) {
    v129_path_check(false, 'safe nested file path is accepted');
}

foreach ([
    ['/link', 'final symlink is rejected'],
    ['/link/secret.txt', 'intermediate symlink is rejected'],
    ['/safe/link.txt', 'nested final symlink is rejected'],
    ['/unknown', 'unknown entry type is rejected'],
] as [$path, $label]) {
    try {
        remote_service_assert_safe_path($provider, $path);
        v129_path_check(false, $label);
    } catch (AppRemoteValidationException $exception) {
        v129_path_check($exception->reason() === 'unsafe_path', $label);
    }
}

try {
    remote_service_assert_safe_path($provider, '/safe/new.txt', true);
    v129_path_check(true, 'missing final upload target is allowed after safe parent check');
} catch (Throwable) {
    v129_path_check(false, 'missing final upload target is allowed after safe parent check');
}

try {
    remote_service_assert_safe_path($provider, '/missing/child.txt', true);
    v129_path_check(false, 'missing intermediate directory is rejected');
} catch (AppRemoteValidationException $exception) {
    v129_path_check($exception->reason() === 'invalid_path', 'missing intermediate directory is rejected');
}

try {
    remote_service_assert_safe_path($provider, '/safe/file.txt', false, true);
    v129_path_check(false, 'directory navigation rejects a file as final component');
} catch (AppRemoteValidationException $exception) {
    v129_path_check($exception->reason() === 'invalid_path', 'directory navigation rejects a file as final component');
}

echo "RESULT: PASS {$pass} / FAIL {$fail}\n";
exit($fail === 0 ? 0 : 1);
