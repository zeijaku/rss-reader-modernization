<?php

declare(strict_types=1);

$cacheRoot = sys_get_temp_dir() . '/iguguru-v115-' . bin2hex(random_bytes(4));
define('APP_WEATHER_CACHE_DIR', $cacheRoot . '/weather');
define('APP_WEATHER_CACHE_TTL_SECONDS', 1800);
define('APP_WEATHER_FORECAST_URL', 'https://api.open-meteo.com/v1/forecast');
define('APP_WEATHER_GEOCODING_URL', 'https://geocoding-api.open-meteo.com/v1/search');
define('APP_HTTP_MAX_REDIRECTS', 3);
define('APP_HTTP_CONNECT_TIMEOUT_MS', 3000);
define('APP_WEATHER_TIMEOUT_MS', 5000);
define('APP_HTTP_USER_AGENT', 'test');

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty): ?string
{
    if (!is_string($value) || strlen($value) > $maxLength) return null;
    $value = trim($value);
    if (!$allowEmpty && $value === '') return null;
    return $value;
}
function app_validate_positive_int(mixed $value): ?int
{
    if (is_int($value)) return $value > 0 ? $value : null;
    if (!is_string($value) || preg_match('/\A[1-9][0-9]*\z/D', $value) !== 1) return null;
    return (int) $value;
}
function dashboard_widget_decode_config(mixed $value): array
{
    if (!is_string($value) || $value === '') return [];
    try { $decoded = json_decode($value, true, 32, JSON_THROW_ON_ERROR); } catch (Throwable) { return []; }
    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
}
function dashboard_widget_encode_config(array $config): string
{
    return json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function dashboard_widget_validate_location(mixed $value): ?int
{
    $v = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3]]);
    return is_int($v) ? $v : null;
}
function dashboard_widget_validate_width(mixed $value): ?int
{
    $v = (int) $value;
    return in_array($v, [1,2,3,4], true) ? $v : null;
}
function dashboard_widget_validate_height(mixed $value): ?int
{
    $v = (int) $value;
    return in_array($v, [1,2], true) ? $v : null;
}
function app_normalize_content_style(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['success','primary','info','secondary','dark','warning','danger'], true) ? $value : null;
}
function db_table_identifier(string $name): string { return '`ig_' . $name . '`'; }
function app_now(): string { return '2026-08-16 00:00:00'; }

final class V115Statement extends PDOStatement
{
    public string $sql = '';
    public array $params = [];
    public mixed $row = null;
    public mixed $column = false;
    public function __construct(string $sql = '') { $this->sql = $sql; }
    public function execute(?array $params = null): bool { $this->params = $params ?? []; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->row; }
    public function fetchColumn(int $column = 0): mixed { return $this->column; }
}
final class V115Pdo extends PDO
{
    public bool $transaction = false;
    public array $statements = [];
    public int $lastId = 99;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return 'fake'; }
    public function inTransaction(): bool { return $this->transaction; }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $s = new V115Statement($query);
        if (str_starts_with($query, 'SELECT widget_id, widget_location')) {
            $s->row = [
                'widget_id'=>8,'widget_location'=>0,'widget_width'=>1,'widget_height'=>1,
                'widget_style'=>'info','widget_config'=>'{"schema":1,"title":"Weather","location_query":"広島市","location_name":"広島市 / 広島県 / 日本","latitude":34.3853,"longitude":132.4553,"timezone":"Asia/Tokyo","forecast_days":3}'
            ];
        }
        $this->statements[] = $s;
        return $s;
    }
    public function lastInsertId(?string $name = null): string|false { return (string) $this->lastId; }
}
$GLOBALS['v115_pdo'] = new V115Pdo();
function conn_db(string $type = ''): PDO { return $GLOBALS['v115_pdo']; }
function dashboard_widget_next_sort_order(PDO $pdo, int $ownerId, int $location): int { return 10; }
function dashboard_widget_lock_owned_widget(PDO $pdo, int $ownerId, int $widgetId, string $type): ?array
{
    return ['widget_id'=>$widgetId,'widget_owner'=>$ownerId,'widget_type'=>$type,'widget_flag'=>0];
}

$root = dirname(__DIR__);
require $root . '/app/information_widget.php';
require $root . '/app/weather.php';
require $root . '/app/sun_moon.php';
require $root . '/app/air_quality.php';
require $root . '/app/earthquake.php';

$pass = 0; $fail = 0;
function v115_check(bool $ok, string $name): void
{
    global $pass, $fail;
    if ($ok) { $pass++; echo "PASS: $name\n"; }
    else { $fail++; echo "FAIL: $name\n"; }
}
function v115_rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $dir . '/' . $name;
        is_dir($path) ? v115_rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

v115_check(information_widget_validate_location_query(' 広島市 ') === '広島市', 'shared location query trims');
v115_check(information_widget_validate_location_query('') === null, 'shared location query rejects empty');
v115_check(information_widget_validate_latitude('-90') === -90.0 && information_widget_validate_latitude('90') === 90.0, 'latitude boundaries');
v115_check(information_widget_validate_latitude(90.01) === null, 'latitude rejects overflow');
v115_check(information_widget_validate_longitude('-180') === -180.0 && information_widget_validate_longitude('180') === 180.0, 'longitude boundaries');
v115_check(information_widget_validate_longitude(180.01) === null, 'longitude rejects overflow');
v115_check(information_widget_validate_timezone('Asia/Tokyo') === 'Asia/Tokyo', 'timezone accepts Asia/Tokyo');
v115_check(information_widget_validate_timezone('../Tokyo') === null, 'timezone rejects invalid syntax');
v115_check(weather_widget_validate_location_query('広島市') === '広島市', 'Weather public validation wrapper remains compatible');

$geo = static fn(string $q): array => ['name'=>$q.' / 広島県 / 日本','latitude'=>34.3853,'longitude'=>132.4553,'timezone'=>'Asia/Tokyo'];
$weatherConfig = weather_widget_config_from_input(['weather_title'=>'Weather','weather_location'=>'広島市','weather_forecast_days'=>'3'], $geo);
$sunConfig = sun_moon_widget_config_from_input(['sun_moon_title'=>'Sun / Moon','sun_moon_location'=>'広島市'], $geo);
$airConfig = air_quality_widget_config_from_input(['air_quality_title'=>'Air Quality','air_quality_location'=>'広島市'], $geo);
v115_check(is_array($weatherConfig) && $weatherConfig['location_name']==='広島市 / 広島県 / 日本', 'Weather uses shared location');
v115_check(is_array($sunConfig) && $sunConfig['latitude']===34.3853, 'Sun Moon uses shared location');
v115_check(is_array($airConfig) && $airConfig['timezone']==='Asia/Tokyo', 'Air Quality uses shared location');

v115_check(air_quality_aqi_label(0)==='良好' && air_quality_aqi_label(50)==='良好', 'US AQI good boundary');
v115_check(air_quality_aqi_label(51)==='普通' && air_quality_aqi_label(100)==='普通', 'US AQI moderate boundary');
v115_check(air_quality_aqi_label(101)==='敏感な人は注意' && air_quality_aqi_label(150)==='敏感な人は注意', 'US AQI sensitive boundary');
v115_check(air_quality_aqi_label(151)==='健康に悪い' && air_quality_aqi_label(200)==='健康に悪い', 'US AQI unhealthy boundary');
v115_check(air_quality_aqi_label(201)==='非常に悪い' && air_quality_aqi_label(300)==='非常に悪い', 'US AQI very unhealthy boundary');
v115_check(air_quality_aqi_label(301)==='危険', 'US AQI hazardous boundary');
v115_check(air_quality_uv_label(2.9)==='弱い' && air_quality_uv_label(3.0)==='中程度', 'UV low/moderate boundary');
v115_check(air_quality_uv_label(6.0)==='強い' && air_quality_uv_label(8.0)==='非常に強い' && air_quality_uv_label(11.0)==='極端に強い', 'UV high boundaries');

$airBody = json_encode(['current'=>['time'=>'2026-08-16T00:00','us_aqi'=>42,'pm2_5'=>8.04,'pm10'=>15.06,'uv_index'=>7.0]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$airParsed = air_quality_parse_current((string) $airBody, $airConfig);
v115_check(is_array($airParsed) && $airParsed['us_aqi']===42 && $airParsed['pm2_5']===8.0 && $airParsed['pm10']===15.1 && $airParsed['uv_label']==='強い', 'Air Quality current parser');
v115_check(air_quality_parse_current('{bad', $airConfig) === null, 'Air Quality invalid JSON rejected');

$tz = new DateTimeZone('Asia/Tokyo');
$sunCurrent = sun_moon_current($sunConfig, (new DateTimeImmutable('2026-08-15 12:00:00', $tz))->getTimestamp());
v115_check(is_string($sunCurrent['sunrise'] ?? null) && is_string($sunCurrent['sunset'] ?? null), 'Sun Moon sunrise and sunset calculate');
v115_check(isset($sunCurrent['moon']['age_days']) && is_numeric($sunCurrent['moon']['age_days']), 'Sun Moon moon age calculates');
v115_check(isset($sunCurrent['moon']['next_full_moon_at']) && is_string($sunCurrent['moon']['next_full_moon_at']), 'Sun Moon next full moon calculates');

v115_check(earthquake_normalize_intensity('5-') === '震度5弱' && earthquake_normalize_intensity('6+') === '震度6強', 'Earthquake intensity normalization');
v115_check(earthquake_validate_jma_detail_url('https://www.data.jma.go.jp/developer/xml/data/20260815000000_0_VXSE53_000000.xml') !== null, 'Earthquake accepts official JMA detail URL');
v115_check(earthquake_validate_jma_detail_url('https://example.com/developer/xml/data/a.xml') === null, 'Earthquake rejects foreign host');
v115_check(earthquake_xml_is_safe('<Report><Head><Title>test</Title></Head></Report>'), 'Earthquake accepts safe XML');
v115_check(!earthquake_xml_is_safe('<!DOCTYPE x [<!ENTITY y SYSTEM "file:///etc/passwd">]><x>&y;</x>'), 'Earthquake rejects DTD/ENTITY XML');

@mkdir(APP_WEATHER_CACHE_DIR, 0750, true);
$weatherPayload = ['location_name'=>'広島市','timezone'=>'Asia/Tokyo','current'=>['temperature'=>30.1,'weather_code'=>1,'label'=>'晴れ','icon'=>'fas fa-sun'],'days'=>[['date'=>'2026-08-15','weather_code'=>1,'label'=>'晴れ','icon'=>'fas fa-sun','temperature_max'=>34.0,'temperature_min'=>25.0,'precipitation_probability'=>10]],'updated_at'=>'2026-08-15T14:30:00Z','stale'=>false];
weather_cache_write($weatherConfig, $weatherPayload);
v115_check((weather_cache_read($weatherConfig, false)['current']['temperature'] ?? null) === 30.1, 'Weather shared cache remains readable');
$airPayload = ['location_name'=>'広島市','timezone'=>'Asia/Tokyo','observed_at'=>'2026-08-16T00:00','us_aqi'=>42,'aqi_label'=>'良好','pm2_5'=>8.0,'pm10'=>15.1,'uv_index'=>7.0,'uv_label'=>'強い','updated_at'=>'2026-08-15T15:00:00Z','stale'=>false];
air_quality_cache_write($airConfig, $airPayload);
v115_check((air_quality_cache_read($airConfig, false)['us_aqi'] ?? null) === 42, 'Air Quality shared cache remains readable');

v115_rrmdir($cacheRoot);
echo "SUMMARY PASS=$pass FAIL=$fail\n";
exit($fail === 0 ? 0 : 1);
