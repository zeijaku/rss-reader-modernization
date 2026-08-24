<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_DEBUG=false');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
putenv('DB_DRIVER=mysql');
putenv('DB_TABLE_PREFIX=ig_');

require_once $root . '/app/common/common_conf.php';
require_once $root . '/app/common/common_db.php';
require_once $root . '/app/validation.php';
require_once $root . '/app/dashboard_widget.php';
require_once $root . '/app/mini_game.php';

$checks = 0;
$failures = 0;
function v16c_check(bool $condition, string $message): void
{
    global $checks, $failures;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures++;
    }
}

v16c_check(mini_game_widget_types() === ['icon_quest', 'lights_out', 'wire_defense', 'block_collapse'], 'Game subtype order keeps existing types and adds Block Collapse');
v16c_check(mini_game_widget_validate_type('lights_out') === 'lights_out', 'Lights Out is accepted by the existing Game validator');
$config = mini_game_widget_config_from_input(['game_title' => 'Lights Out', 'game_type' => 'lights_out']);
v16c_check($config === ['schema' => 1, 'title' => 'Lights Out', 'game' => 'lights_out'], 'Lights Out uses the existing widget_config schema');
$stored = mini_game_widget_config_from_storage('{"schema":1,"title":"Lights Out","game":"lights_out"}');
v16c_check($stored === ['schema' => 1, 'title' => 'Lights Out', 'game' => 'lights_out'], 'stored Lights Out config restores without schema change');
v16c_check(mini_game_widget_validate_type('lights-out') === null, 'unapproved Lights Out spelling is rejected');

v16c_check(mini_game_widget_validate_type('wire_defense') === 'wire_defense', 'Wire Defense is accepted by the existing Game validator');
$wireConfig = mini_game_widget_config_from_input(['game_title' => 'Wire Defense', 'game_type' => 'wire_defense']);
v16c_check($wireConfig === ['schema' => 1, 'title' => 'Wire Defense', 'game' => 'wire_defense'], 'Wire Defense uses the existing widget_config schema');
v16c_check(mini_game_widget_validate_type('wire-defense') === null, 'unapproved Wire Defense spelling is rejected');

printf("RESULT: %s %d / FAIL %d / SKIP 0\n", $failures === 0 ? 'PASS' : 'FAIL', $checks, $failures);
exit($failures === 0 ? 0 : 1);
