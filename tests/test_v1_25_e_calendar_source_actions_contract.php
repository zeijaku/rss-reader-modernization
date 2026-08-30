<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sourceActions = file_get_contents($root . '/public/js/calendar-source-actions.js');
$loader = file_get_contents($root . '/public/js/calendar.js');
$version = file_get_contents($root . '/app/version.php');

foreach ([$sourceActions, $loader, $version] as $source) {
    if (!is_string($source)) {
        fwrite(STDERR, "FAIL: V1.25-E source read\n");
        exit(1);
    }
}

$appVersion = null;
$assetRevision = null;
if (preg_match("/const APP_VERSION = '([^']+)';/", $version, $versionMatch) === 1) {
    $appVersion = $versionMatch[1];
}
if (preg_match("/const APP_ASSET_REVISION = '([^']+)';/", $version, $assetMatch) === 1) {
    $assetRevision = $assetMatch[1];
}

$checks = [
    'formal APP_VERSION is defined' => is_string($appVersion) && preg_match('/^\d+\.\d+\.\d+$/', $appVersion) === 1,
    'formal asset revision follows APP_VERSION' => is_string($assetRevision) && $assetRevision === $appVersion,
    'source action module uses current release cache key' => is_string($assetRevision)
        && str_contains($loader, 'calendar-source-actions.js?v=' . $assetRevision),
    'article action menu is reused' => str_contains($sourceActions, "$('#articleActionsMenu')"),
    'Calendar action is added after Task when available' => str_contains($sourceActions, ".article-action-task")
        && str_contains($sourceActions, "insertAdjacentElement('afterend', button)"),
    'Calendar action label is present' => str_contains($sourceActions, "text.textContent = 'Calendarへ追加';"),
    'Calendar action icon is present' => str_contains($sourceActions, "fas fa-calendar-plus"),
    'article title comes from existing menu context' => str_contains($sourceActions, "\$menu.data('article-title')"),
    'article URL comes from existing menu context' => str_contains($sourceActions, "\$menu.data('article-url')"),
    'title is bounded to Calendar title limit' => str_contains($sourceActions, ".slice(0, 128)"),
    'URL is bounded to Calendar URL limit' => str_contains($sourceActions, 'url.length > 2048'),
    'only http/https URL is prefilled' => str_contains($sourceActions, '!/^https?:\\/\\//i.test(url)'),
    'existing Calendar add trigger resets add form' => str_contains($sourceActions, "button.className = 'calendar-event-add-trigger';")
        && str_contains($sourceActions, "button.setAttribute('data-calendar-date', localDateString(new Date()));"),
    'existing Calendar title field is prefilled' => str_contains($sourceActions, "$('.registerCalendarEventTitleValue').val(title);"),
    'V1.25-C URL field is prefilled' => str_contains($sourceActions, "$('.registerCalendarEventUrl').val(url);"),
    'existing Calendar modal is reused' => str_contains($sourceActions, "getElementById('registerCalendarEvent')"),
    'Bootstrap 5 modal path is supported' => str_contains($sourceActions, 'window.bootstrap.Modal.getOrCreateInstance(modalEl).show()'),
    'legacy Bootstrap modal fallback is retained' => str_contains($sourceActions, "$(modalEl).modal('show');"),
    'source action does not submit Calendar automatically' => !str_contains($sourceActions, '.submit(')
        && !str_contains($sourceActions, 'requestSubmit(')
        && !str_contains($sourceActions, '.trigger(\'submit\')'),
    'source action performs no AJAX or outbound fetch' => !str_contains($sourceActions, '$.ajax(')
        && !str_contains($sourceActions, 'fetch(')
        && !str_contains($sourceActions, 'XMLHttpRequest'),
    'source action does not assign innerHTML' => !preg_match('/\.innerHTML\s*=/', $sourceActions),
    'source action does not use eval' => !preg_match('/\beval\s*\(/', $sourceActions),
];

$corePos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-core.js?v={$assetRevision}');") : false;
$repeatPos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-recurrence.js?v={$assetRevision}');") : false;
$detailPos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-event-details.js?v={$assetRevision}');") : false;
$colorPos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-colors.js?v={$assetRevision}');") : false;
$sourcePos = is_string($assetRevision) ? strpos($loader, "loadScript('./js/calendar-source-actions.js?v={$assetRevision}');") : false;
$checks['source action loads after Calendar core/detail/recurrence/color layers'] = is_int($corePos)
    && is_int($repeatPos)
    && is_int($detailPos)
    && is_int($colorPos)
    && is_int($sourcePos)
    && $corePos < $repeatPos
    && $repeatPos < $detailPos
    && $detailPos < $colorPos
    && $colorPos < $sourcePos;

$failed = [];
foreach ($checks as $name => $passed) {
    if ($passed) {
        fwrite(STDOUT, "PASS: {$name}\n");
    } else {
        fwrite(STDERR, "FAIL: {$name}\n");
        $failed[] = $name;
    }
}

if ($failed !== []) {
    exit(1);
}

echo 'PASS: V1.25-E Calendar source actions contract (' . count($checks) . " checks)\n";
