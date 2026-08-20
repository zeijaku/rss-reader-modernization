/* V1.18-E: Connection Monitor / health_probe UI, responsive and polling finalization; preserves V1.18-D state semantics. */
(function ($, window, document) {
    'use strict';

    if (window.iGuguruHealthProbeWidget) {
        return;
    }

    var common = window.iGuguruInformationWidgetCommon;
    if (!common) {
        return;
    }

    var namespace = '.iguguruHealthProbeWidget';
    var probeIntervalMs = 5000;
    var probeTimeoutMs = 4000;
    var probeTimer = null;
    var probePending = false;
    var lastProbeResult = null;
    var historyRetentionMs = 300000;
    var historyMaxSamples = 120;
    var defaultHistoryWindowMs = 60000;
    var probeHistory = [];
    var offlineConfirmFailures = 2;
    var recoveryNoticeMs = 15000;
    var monitorState = {
        consecutiveNetworkFailures: 0,
        pendingFailureAt: null,
        outageStartedAt: null,
        lastDisconnectAt: null,
        lastDowntimeMs: null,
        recoveredAt: null,
        relativeSlowStreak: 0
    };
    var downtimeTimer = null;
    var checkedTimeFormatter = null;

    function currentLocation() {
        return common.currentLocation();
    }

    function widthClass(width) {
        return common.widthClass(width);
    }

    function nowMs() {
        if (window.performance && typeof window.performance.now === 'function') {
            return window.performance.now();
        }
        return Date.now();
    }

    function browserOnlineHint() {
        if (!window.navigator || typeof window.navigator.onLine !== 'boolean') {
            return null;
        }
        return window.navigator.onLine;
    }

    function browserHintLabel(value) {
        if (value === true) {
            return '端末判定 Online';
        }
        if (value === false) {
            return '端末判定 Offline';
        }
        return '端末判定 —';
    }

    function formatCheckedAt(date) {
        try {
            if (checkedTimeFormatter === null && window.Intl && typeof window.Intl.DateTimeFormat === 'function') {
                checkedTimeFormatter = new window.Intl.DateTimeFormat('ja-JP', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
            if (checkedTimeFormatter !== null) {
                return checkedTimeFormatter.format(date);
            }
        } catch (e) {
            checkedTimeFormatter = null;
        }
        return date.toLocaleTimeString ? date.toLocaleTimeString() : '';
    }

    function resultClass(state) {
        if (state === 'online') { return 'is-online'; }
        if (state === 'offline') { return 'is-offline'; }
        if (state === 'error') { return 'is-error'; }
        return 'is-checking';
    }

    function resultLabel(state) {
        if (state === 'online') { return 'Online'; }
        if (state === 'offline') { return 'Offline'; }
        if (state === 'error') { return 'Probe Error'; }
        return 'Checking';
    }

    function resultMessage(result) {
        if (!result) {
            return '接続状態を確認しています';
        }
        if (result.confirmingOffline === true) {
            return '応答を確認出来ませんでした。もう一度確認しています';
        }
        if (result.state === 'online') {
            if (result.recentlyRecovered === true) {
                return '接続が復旧しました';
            }
            if (result.relativeSlow === true) {
                return 'RSS Readerへ到達出来ています（通常より遅い状態です）';
            }
            return 'RSS Readerへ到達出来ています';
        }
        if (result.state === 'offline') {
            return result.timedOut ? '応答がなく、接続断を確認しました' : 'RSS Readerへの接続断を確認しました';
        }
        if (result.state === 'error') {
            return result.httpStatus > 0
                ? 'Probe EndpointがHTTP ' + String(result.httpStatus) + 'を返しました'
                : 'Probe Endpointを確認出来ませんでした';
        }
        return '接続状態を確認しています';
    }

    function historyWindowLabel(windowMs) {
        if (windowMs === 30000) { return '30s'; }
        if (windowMs === 300000) { return '5m'; }
        return '60s';
    }

    function normalizeHistoryWindow(value) {
        var windowMs = Number(value);
        return windowMs === 30000 || windowMs === 60000 || windowMs === 300000
            ? windowMs
            : defaultHistoryWindowMs;
    }

    function cardHistoryWindow($card) {
        return normalizeHistoryWindow($card.data('health-probe-history-window'));
    }

    function trimHistory(referenceMs) {
        var oldest = Number(referenceMs) - historyRetentionMs;
        probeHistory = probeHistory.filter(function (sample) {
            return Number(sample.sampledAt) >= oldest;
        });
        if (probeHistory.length > historyMaxSamples) {
            probeHistory = probeHistory.slice(probeHistory.length - historyMaxSamples);
        }
    }

    function recordProbeResult(result) {
        if (!result || result.state === 'checking') {
            return;
        }
        var sampledAt = result.checkedAt instanceof Date ? result.checkedAt.getTime() : Date.now();
        probeHistory.push({
            state: String(result.state || 'error'),
            latencyMs: result.state === 'online' && Number.isFinite(Number(result.latencyMs))
                ? Math.max(0, Number(result.latencyMs))
                : null,
            httpStatus: Number.isFinite(Number(result.httpStatus)) ? Number(result.httpStatus) : 0,
            sampledAt: sampledAt
        });
        trimHistory(sampledAt);
    }

    function historyEntries(windowMs, referenceMs) {
        var now = Number.isFinite(Number(referenceMs)) ? Number(referenceMs) : Date.now();
        var oldest = now - normalizeHistoryWindow(windowMs);
        return probeHistory.filter(function (sample) {
            return Number(sample.sampledAt) >= oldest && Number(sample.sampledAt) <= now;
        });
    }

    function latencyMetrics(entries) {
        var total = 0;
        var maximum = null;
        var count = 0;
        var jitterTotal = 0;
        var jitterCount = 0;
        var previousLatency = null;
        var previousSampleAt = null;
        var continuityLimitMs = probeIntervalMs * 2.5;

        entries.forEach(function (sample) {
            var latency = sample && sample.state === 'online' ? Number(sample.latencyMs) : NaN;
            var sampledAt = sample ? Number(sample.sampledAt) : NaN;
            if (!Number.isFinite(latency)) {
                previousLatency = null;
                previousSampleAt = null;
                return;
            }
            if (previousSampleAt !== null && Number.isFinite(sampledAt)
                && sampledAt - previousSampleAt > continuityLimitMs) {
                previousLatency = null;
            }
            latency = Math.max(0, latency);
            total += latency;
            maximum = maximum === null ? latency : Math.max(maximum, latency);
            count += 1;
            if (previousLatency !== null) {
                jitterTotal += Math.abs(latency - previousLatency);
                jitterCount += 1;
            }
            previousLatency = latency;
            previousSampleAt = Number.isFinite(sampledAt) ? sampledAt : null;
        });

        return {
            count: count,
            average: count > 0 ? total / count : null,
            maximum: maximum,
            jitter: jitterCount > 0 ? jitterTotal / jitterCount : null
        };
    }

    function roundedMetric(value) {
        if (value === null || value === undefined || value === '') { return '—'; }
        return Number.isFinite(Number(value)) ? String(Math.max(0, Math.round(Number(value)))) : '—';
    }

    function qualityFromLatency(latencyMs) {
        if (latencyMs === null || latencyMs === undefined || latencyMs === '') {
            return {key: 'unknown', label: '—'};
        }
        var latency = Number(latencyMs);
        if (!Number.isFinite(latency)) {
            return {key: 'unknown', label: '—'};
        }
        latency = Math.max(0, latency);
        if (latency <= 79) { return {key: 'excellent', label: 'Excellent'}; }
        if (latency <= 149) { return {key: 'good', label: 'Good'}; }
        if (latency <= 299) { return {key: 'fair', label: 'Fair'}; }
        return {key: 'slow', label: 'Slow'};
    }

    function median(values) {
        var numbers = values.map(Number).filter(function (value) { return Number.isFinite(value); })
            .sort(function (a, b) { return a - b; });
        if (numbers.length === 0) { return null; }
        var middle = Math.floor(numbers.length / 2);
        return numbers.length % 2 === 1
            ? numbers[middle]
            : (numbers[middle - 1] + numbers[middle]) / 2;
    }

    function baselineLatency(referenceMs) {
        var now = Number.isFinite(Number(referenceMs)) ? Number(referenceMs) : Date.now();
        var oldest = now - historyRetentionMs;
        var values = probeHistory.filter(function (sample) {
            return sample && sample.state === 'online'
                && Number.isFinite(Number(sample.latencyMs))
                && Number(sample.sampledAt) >= oldest
                && Number(sample.sampledAt) < now;
        }).map(function (sample) { return Math.max(0, Number(sample.latencyMs)); });
        return values.length >= 5 ? median(values) : null;
    }

    function materiallySlowerThanBaseline(latencyMs, baselineMs) {
        if (latencyMs === null || latencyMs === undefined || baselineMs === null || baselineMs === undefined) {
            return false;
        }
        var latency = Number(latencyMs);
        var baseline = Number(baselineMs);
        return Number.isFinite(latency) && Number.isFinite(baseline)
            && latency > baseline * 2
            && latency - baseline >= 50;
    }

    function currentDowntimeMs(referenceMs) {
        if (monitorState.outageStartedAt === null) { return null; }
        var now = Number.isFinite(Number(referenceMs)) ? Number(referenceMs) : Date.now();
        return Math.max(0, now - monitorState.outageStartedAt);
    }

    function resetPendingFailure() {
        monitorState.consecutiveNetworkFailures = 0;
        monitorState.pendingFailureAt = null;
    }

    function closeOutage(referenceMs) {
        var now = Number.isFinite(Number(referenceMs)) ? Number(referenceMs) : Date.now();
        if (monitorState.outageStartedAt !== null) {
            monitorState.lastDowntimeMs = Math.max(0, now - monitorState.outageStartedAt);
            monitorState.recoveredAt = now;
            monitorState.outageStartedAt = null;
        }
        resetPendingFailure();
    }

    function monitorMetadata(result, referenceMs) {
        var output = Object.assign({}, result || {});
        var now = Number.isFinite(Number(referenceMs)) ? Number(referenceMs) : Date.now();
        output.lastDisconnectAt = monitorState.lastDisconnectAt === null ? null : new Date(monitorState.lastDisconnectAt);
        output.lastDowntimeMs = monitorState.lastDowntimeMs;
        output.currentDowntimeMs = currentDowntimeMs(now);
        output.recoveredAt = monitorState.recoveredAt === null ? null : new Date(monitorState.recoveredAt);
        output.recentlyRecovered = monitorState.recoveredAt !== null
            && now - monitorState.recoveredAt >= 0
            && now - monitorState.recoveredAt <= recoveryNoticeMs;
        return output;
    }

    function applyConnectionState(result) {
        var checkedMs = result && result.checkedAt instanceof Date ? result.checkedAt.getTime() : Date.now();
        var state = result && result.state ? String(result.state) : 'checking';
        var output = Object.assign({}, result || {});

        if (state === 'online') {
            if (monitorState.outageStartedAt !== null) {
                closeOutage(checkedMs);
            } else {
                resetPendingFailure();
            }
            var quality = qualityFromLatency(output.latencyMs);
            var baseline = baselineLatency(checkedMs);
            if (materiallySlowerThanBaseline(output.latencyMs, baseline)) {
                monitorState.relativeSlowStreak += 1;
            } else {
                monitorState.relativeSlowStreak = 0;
            }
            output.qualityKey = quality.key;
            output.qualityLabel = quality.label;
            output.baselineMs = baseline;
            output.relativeSlow = monitorState.relativeSlowStreak >= 2;
            return monitorMetadata(output, checkedMs);
        }

        monitorState.relativeSlowStreak = 0;

        if (state === 'offline') {
            if (monitorState.consecutiveNetworkFailures === 0) {
                monitorState.pendingFailureAt = checkedMs;
            }
            monitorState.consecutiveNetworkFailures += 1;
            if (monitorState.consecutiveNetworkFailures < offlineConfirmFailures) {
                output.state = 'checking';
                output.confirmingOffline = true;
                output.qualityKey = 'checking';
                output.qualityLabel = 'Checking';
                return monitorMetadata(output, checkedMs);
            }
            if (monitorState.outageStartedAt === null) {
                monitorState.outageStartedAt = monitorState.pendingFailureAt === null
                    ? checkedMs
                    : monitorState.pendingFailureAt;
                monitorState.lastDisconnectAt = monitorState.outageStartedAt;
            }
            output.state = 'offline';
            output.confirmedOffline = true;
            output.qualityKey = 'offline';
            output.qualityLabel = 'Offline';
            return monitorMetadata(output, checkedMs);
        }

        if (state === 'error') {
            if (monitorState.outageStartedAt !== null) {
                closeOutage(checkedMs);
            } else {
                resetPendingFailure();
            }
            output.qualityKey = 'unavailable';
            output.qualityLabel = 'Unavailable';
            return monitorMetadata(output, checkedMs);
        }

        output.qualityKey = 'checking';
        output.qualityLabel = 'Checking';
        return monitorMetadata(output, checkedMs);
    }

    function formatDuration(durationMs) {
        if (durationMs === null || durationMs === undefined || durationMs === '') { return '—'; }
        var duration = Number(durationMs);
        if (!Number.isFinite(duration) || duration < 0) { return '—'; }
        var totalSeconds = Math.max(0, Math.floor(duration / 1000));
        var hours = Math.floor(totalSeconds / 3600);
        var minutes = Math.floor((totalSeconds % 3600) / 60);
        var seconds = totalSeconds % 60;
        if (hours > 0) {
            return String(hours) + ':' + String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
        }
        return String(minutes) + ':' + String(seconds).padStart(2, '0');
    }

    function formatEventTime(value) {
        if (!(value instanceof Date) || isNaN(value.getTime())) { return '—'; }
        return formatCheckedAt(value);
    }

    function chartScaleMaximum(entries) {
        var maximum = 0;
        entries.forEach(function (sample) {
            if (sample && sample.state === 'online' && Number.isFinite(Number(sample.latencyMs))) {
                maximum = Math.max(maximum, Math.max(0, Number(sample.latencyMs)));
            }
        });
        if (maximum <= 0) {
            return 100;
        }
        return Math.max(100, Math.ceil((maximum * 1.15) / 50) * 50);
    }

    function svgNode(name, attributes) {
        var node = document.createElementNS('http://www.w3.org/2000/svg', name);
        Object.keys(attributes || {}).forEach(function (key) {
            node.setAttribute(key, String(attributes[key]));
        });
        return node;
    }

    function historyGraph(entries, windowMs, referenceMs) {
        var width = 100;
        var height = 36;
        var top = 3;
        var bottom = 33;
        var now = Number(referenceMs);
        var start = now - windowMs;
        var scaleMaximum = chartScaleMaximum(entries);
        var $wrap = $('<div>').addClass('health-probe-chart-wrap');
        var svg = svgNode('svg', {
            class: 'health-probe-chart',
            viewBox: '0 0 ' + width + ' ' + height,
            preserveAspectRatio: 'none',
            role: 'img',
            'aria-label': '過去' + historyWindowLabel(windowMs) + 'のLatency推移'
        });

        [top, (top + bottom) / 2, bottom].forEach(function (y) {
            svg.appendChild(svgNode('line', {
                x1: 0, y1: y, x2: width, y2: y, class: 'health-probe-chart-grid'
            }));
        });

        var segment = [];
        var segments = [];
        var singlePoints = [];
        var previousSampleAt = null;
        var continuityLimitMs = probeIntervalMs * 2.5;
        function flushSegment() {
            if (segment.length >= 2) {
                segments.push(segment);
            } else if (segment.length === 1) {
                singlePoints.push(segment[0]);
            }
            segment = [];
        }

        entries.forEach(function (sample) {
            var latency = sample && sample.state === 'online' ? Number(sample.latencyMs) : NaN;
            var sampledAt = sample ? Number(sample.sampledAt) : NaN;
            if (!Number.isFinite(latency) || !Number.isFinite(sampledAt)) {
                flushSegment();
                previousSampleAt = null;
                return;
            }
            if (previousSampleAt !== null && sampledAt - previousSampleAt > continuityLimitMs) {
                flushSegment();
            }
            var x = ((sampledAt - start) / windowMs) * width;
            var normalized = Math.min(1, Math.max(0, latency) / scaleMaximum);
            var y = bottom - (normalized * (bottom - top));
            segment.push([Math.max(0, Math.min(width, x)), y]);
            previousSampleAt = sampledAt;
        });
        flushSegment();

        segments.forEach(function (points) {
            svg.appendChild(svgNode('polyline', {
                points: points.map(function (point) {
                    return point[0].toFixed(2) + ',' + point[1].toFixed(2);
                }).join(' '),
                class: 'health-probe-chart-line',
                fill: 'none'
            }));
        });
        singlePoints.forEach(function (point) {
            svg.appendChild(svgNode('circle', {
                cx: point[0].toFixed(2), cy: point[1].toFixed(2), r: 1.1, class: 'health-probe-chart-point'
            }));
        });

        $wrap.append(svg);
        $wrap.append($('<span>').addClass('health-probe-chart-scale text-muted').text('0–' + String(scaleMaximum) + ' ms'));
        if (entries.every(function (sample) {
            return !sample || sample.state !== 'online' || !Number.isFinite(Number(sample.latencyMs));
        })) {
            $wrap.append($('<span>').addClass('health-probe-chart-empty text-muted').text('履歴を収集中'));
        }
        return $wrap;
    }

    function historyStat(label, value) {
        return $('<div>').addClass('health-probe-stat')
            .append($('<span>').addClass('health-probe-stat-label text-muted').text(label))
            .append($('<span>').addClass('health-probe-stat-value').text(roundedMetric(value) + ' ms'));
    }

    function eventStat(label, value, valueClass) {
        return $('<div>').addClass('health-probe-event-stat')
            .append($('<span>').addClass('health-probe-event-label text-muted').text(label))
            .append($('<span>').addClass('health-probe-event-value' + (valueClass ? ' ' + valueClass : '')).text(value));
    }

    function renderConnectionState(result) {
        result = result || {};
        var qualityKey = String(result.qualityKey || 'checking');
        var qualityLabel = String(result.qualityLabel || 'Checking');
        var $panel = $('<section>').addClass('health-probe-connection-state').attr('aria-label', '接続品質と切断情報');
        var $qualityValue = $('<div>').addClass('health-probe-quality-value')
            .append($('<span>').addClass('health-probe-quality-badge is-' + qualityKey).text(qualityLabel));
        var $flags = $('<span>').addClass('health-probe-status-flags');
        if (result.relativeSlow === true) {
            $flags.append($('<span>').addClass('health-probe-relative-slow').text('通常より遅い'));
        }
        if (result.recentlyRecovered === true) {
            $flags.append($('<span>').addClass('health-probe-recovered').text('Recovered'));
        }
        if ($flags.children().length > 0) {
            $qualityValue.append($flags);
        }
        var $quality = $('<div>').addClass('health-probe-quality-row')
            .append($('<span>').addClass('health-probe-quality-label text-muted').text('Connection Quality'))
            .append($qualityValue);

        var baselineText = result.baselineMs !== null && result.baselineMs !== undefined
            && Number.isFinite(Number(result.baselineMs))
            ? 'Baseline ' + roundedMetric(result.baselineMs) + ' ms'
            : 'Baseline 学習中';
        var $baseline = $('<div>').addClass('health-probe-baseline text-muted').text(baselineText);

        var downtimeValue = result.state === 'offline'
            ? formatDuration(result.currentDowntimeMs)
            : formatDuration(result.lastDowntimeMs);
        var downtimeLabel = result.state === 'offline' ? 'Downtime' : 'Last Downtime';
        var $events = $('<div>').addClass('health-probe-events').append(
            eventStat('Last Disconnect', formatEventTime(result.lastDisconnectAt)),
            eventStat(downtimeLabel, downtimeValue, result.state === 'offline' ? 'health-probe-current-downtime' : '')
        );
        return $panel.append($quality, $baseline, $events);
    }

    function renderHistory($card) {
        var windowMs = cardHistoryWindow($card);
        var referenceMs = Date.now();
        var entries = historyEntries(windowMs, referenceMs);
        var metrics = latencyMetrics(entries);
        var $history = $('<section>').addClass('health-probe-history').attr('aria-label', 'Latency履歴');
        var $head = $('<div>').addClass('health-probe-history-head');
        var $buttons = $('<div>').addClass('btn-group btn-group-sm health-probe-history-buttons').attr('role', 'group');
        [30000, 60000, 300000].forEach(function (value) {
            var active = value === windowMs;
            $buttons.append(
                $('<button>')
                    .attr({
                        type: 'button',
                        'data-health-probe-window-ms': String(value),
                        'aria-pressed': active ? 'true' : 'false',
                        'aria-label': 'Latency履歴を' + historyWindowLabel(value) + 'で表示'
                    })
                    .addClass('btn btn-outline-secondary health-probe-window-trigger' + (active ? ' active' : ''))
                    .text(historyWindowLabel(value))
            );
        });
        $head.append($('<span>').addClass('health-probe-history-title').text('Latency History'), $buttons);
        $history.append(
            $head,
            historyGraph(entries, windowMs, referenceMs),
            $('<div>').addClass('health-probe-stats').append(
                historyStat('Avg', metrics.average),
                historyStat('Max', metrics.maximum),
                historyStat('Jitter', metrics.jitter)
            )
        );
        return $history;
    }

    function renderCard($card, result) {
        var state = result && result.state ? result.state : 'checking';
        var $body = $card.find('.health-probe-card-body').first();
        if ($body.length === 0) {
            return;
        }

        $card.removeClass('health-probe-online health-probe-offline health-probe-error health-probe-checking')
            .addClass('health-probe-' + state)
            .attr('aria-busy', state === 'checking' ? 'true' : 'false');

        var $summary = $('<div>').addClass('health-probe-summary ' + resultClass(state));
        var $state = $('<div>').addClass('health-probe-state');
        $state.append(
            $('<span>').addClass('health-probe-dot').attr('aria-hidden', 'true'),
            $('<span>').addClass('health-probe-state-label').text(resultLabel(state))
        );

        var $latency = $('<div>').addClass('health-probe-latency');
        if (result && result.state === 'online' && Number.isFinite(Number(result.latencyMs))) {
            $latency.append(
                $('<span>').addClass('health-probe-latency-value').text(String(Math.max(0, Math.round(Number(result.latencyMs))))),
                $('<span>').addClass('health-probe-latency-unit').text('ms')
            );
        } else {
            $latency.append(
                $('<span>').addClass('health-probe-latency-value').text('—'),
                $('<span>').addClass('health-probe-latency-unit').text('ms')
            );
        }
        $summary.append($state, $latency);

        var checkedAt = result && result.checkedAt instanceof Date ? result.checkedAt : null;
        var $meta = $('<div>').addClass('health-probe-meta text-muted')
            .append($('<span>').text(browserHintLabel(result ? result.browserOnline : browserOnlineHint())));
        if (checkedAt) {
            $meta.append($('<span>').text('更新 ' + formatCheckedAt(checkedAt)));
        }

        $body.empty().append(
            $summary,
            $('<div>').addClass('health-probe-message').text(resultMessage(result)),
            renderConnectionState(result),
            renderHistory($card),
            $('<div>').addClass('health-probe-route text-muted').text('Browser → RSS Reader'),
            $meta
        );
    }

    function renderAll(result) {
        $('.health-probe-card').each(function () {
            renderCard($(this), result);
        });
    }

    function publish(result) {
        var displayResult = applyConnectionState(result);
        lastProbeResult = displayResult;
        recordProbeResult(result);
        renderAll(displayResult);
        syncDowntimeTimer();
    }

    function healthProbeCardsExist() {
        return $('.health-probe-card').length > 0;
    }

    function clearProbeTimer() {
        if (probeTimer !== null) {
            window.clearTimeout(probeTimer);
            probeTimer = null;
        }
    }

    function clearDowntimeTimer() {
        if (downtimeTimer !== null) {
            window.clearInterval(downtimeTimer);
            downtimeTimer = null;
        }
    }

    function updateDowntimeLabels() {
        var duration = currentDowntimeMs(Date.now());
        if (duration === null) { return; }
        $('.health-probe-current-downtime').text(formatDuration(duration));
    }

    function syncDowntimeTimer() {
        clearDowntimeTimer();
        if (monitorState.outageStartedAt === null || document.hidden || !healthProbeCardsExist()) {
            return;
        }
        updateDowntimeLabels();
        downtimeTimer = window.setInterval(updateDowntimeLabels, 1000);
    }

    function scheduleProbe() {
        clearProbeTimer();
        if (!healthProbeCardsExist() || document.hidden) {
            return;
        }
        probeTimer = window.setTimeout(runProbe, probeIntervalMs);
    }

    function setRefreshPending(pending) {
        var isPending = pending === true;
        $('.health-probe-refresh-trigger')
            .prop('disabled', isPending)
            .attr('aria-busy', isPending ? 'true' : 'false')
            .each(function () {
                $(this).find('i').toggleClass('fa-spin', isPending);
            });
    }

    function runProbe() {
        clearProbeTimer();
        if (probePending || !healthProbeCardsExist() || document.hidden) {
            return;
        }

        probePending = true;
        setRefreshPending(true);
        var startedAt = nowMs();
        if (lastProbeResult === null) {
            renderAll({state: 'checking', browserOnline: browserOnlineHint()});
        }

        $.ajax({
            url: './connection_probe.php',
            method: 'GET',
            cache: false,
            dataType: 'text',
            timeout: probeTimeoutMs
        })
            .done(function (_body, _textStatus, xhr) {
                var elapsed = Math.max(0, nowMs() - startedAt);
                var status = xhr && Number.isFinite(Number(xhr.status)) ? Number(xhr.status) : 0;
                if (status === 204) {
                    publish({
                        state: 'online',
                        latencyMs: elapsed,
                        httpStatus: status,
                        browserOnline: browserOnlineHint(),
                        checkedAt: new Date()
                    });
                    return;
                }
                publish({
                    state: 'error',
                    latencyMs: null,
                    httpStatus: status,
                    browserOnline: browserOnlineHint(),
                    checkedAt: new Date()
                });
            })
            .fail(function (xhr, textStatus) {
                var status = xhr && Number.isFinite(Number(xhr.status)) ? Number(xhr.status) : 0;
                publish({
                    state: status > 0 ? 'error' : 'offline',
                    latencyMs: null,
                    httpStatus: status,
                    timedOut: textStatus === 'timeout',
                    browserOnline: browserOnlineHint(),
                    checkedAt: new Date()
                });
            })
            .always(function () {
                probePending = false;
                setRefreshPending(false);
                scheduleProbe();
            });
    }

    function probeNow() {
        if (document.hidden || !healthProbeCardsExist()) {
            return;
        }
        if (!probePending) {
            runProbe();
        }
    }

    function addStyles() {
        if ($('#v118e-health-probe-styles').length > 0) {
            return;
        }
        var css = ''
            + '.health-probe-card .health-probe-card-body{display:flex;flex-direction:column;gap:.46rem;min-height:0}'
            + '.health-probe-summary{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.12rem 0}'
            + '.health-probe-state{display:flex;align-items:center;gap:.48rem;min-width:0;font-size:1rem;font-weight:600}'
            + '.health-probe-dot{display:inline-block;width:.72rem;height:.72rem;flex:0 0 .72rem;border-radius:50%;background:var(--bs-secondary,#6c757d);box-shadow:0 0 0 .2rem rgba(var(--bs-secondary-rgb,108,117,125),.12)}'
            + '.health-probe-summary.is-online .health-probe-dot{background:var(--bs-success,#198754);box-shadow:0 0 0 .2rem rgba(var(--bs-success-rgb,25,135,84),.14)}'
            + '.health-probe-summary.is-offline .health-probe-dot{background:var(--bs-danger,#dc3545);box-shadow:0 0 0 .2rem rgba(var(--bs-danger-rgb,220,53,69),.14)}'
            + '.health-probe-summary.is-error .health-probe-dot{background:var(--bs-warning,#ffc107);box-shadow:0 0 0 .2rem rgba(var(--bs-warning-rgb,255,193,7),.16)}'
            + '.health-probe-latency{display:flex;align-items:baseline;justify-content:flex-end;gap:.18rem;white-space:nowrap;font-variant-numeric:tabular-nums}'
            + '.health-probe-latency-value{font-size:1.7rem;font-weight:700;line-height:1}'
            + '.health-probe-latency-unit{font-size:.72rem;color:var(--bs-secondary-color,#6c757d)}'
            + '.health-probe-message{font-size:.8rem;line-height:1.35;overflow-wrap:anywhere}'
            + '.health-probe-connection-state{display:flex;flex-direction:column;gap:.28rem;padding:.38rem .42rem;border:1px solid var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18));border-radius:.3rem;background:var(--bs-tertiary-bg,rgba(var(--bs-body-color-rgb,33,37,41),.05));color:var(--bs-body-color,#212529)}'
            + '.health-probe-quality-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem;min-width:0}'
            + '.health-probe-quality-label{font-size:.66rem;white-space:nowrap}'
            + '.health-probe-quality-value{display:flex;align-items:center;justify-content:flex-end;gap:.28rem;min-width:0;flex-wrap:wrap}'
            + '.health-probe-quality-badge{display:inline-flex;align-items:center;justify-content:center;min-width:4.9rem;padding:.13rem .4rem;border:1px solid currentColor;border-radius:999px;font-size:.68rem;font-weight:700;line-height:1.3}'
            + '.health-probe-quality-badge.is-excellent{color:var(--bs-success-text-emphasis,var(--bs-success,#198754));background:var(--bs-success-bg-subtle,transparent)}'
            + '.health-probe-quality-badge.is-good{color:var(--bs-primary-text-emphasis,var(--bs-primary,#0d6efd));background:var(--bs-primary-bg-subtle,transparent)}'
            + '.health-probe-quality-badge.is-fair{color:var(--bs-warning-text-emphasis,#997404);background:var(--bs-warning-bg-subtle,transparent)}'
            + '.health-probe-quality-badge.is-slow,.health-probe-quality-badge.is-offline{color:var(--bs-danger-text-emphasis,var(--bs-danger,#dc3545));background:var(--bs-danger-bg-subtle,transparent)}'
            + '.health-probe-quality-badge.is-unavailable{color:var(--bs-warning-text-emphasis,#997404);background:var(--bs-warning-bg-subtle,transparent)}'
            + '.health-probe-quality-badge.is-checking,.health-probe-quality-badge.is-unknown{color:var(--bs-secondary-color,#6c757d);background:var(--bs-secondary-bg-subtle,var(--bs-tertiary-bg,transparent))}'
            + '.health-probe-status-flags{display:inline-flex;align-items:center;gap:.22rem;flex-wrap:wrap}'
            + '.health-probe-baseline{display:flex;align-items:center;gap:.35rem;min-height:1rem;font-size:.63rem;line-height:1.25;flex-wrap:wrap}'
            + '.health-probe-relative-slow{padding:.08rem .28rem;border-radius:.2rem;color:var(--bs-danger-text-emphasis,var(--bs-danger,#dc3545));background:var(--bs-danger-bg-subtle,transparent);border:1px solid currentColor;font-size:.62rem;font-weight:600;white-space:nowrap}'
            + '.health-probe-recovered{padding:.08rem .28rem;border-radius:.2rem;color:var(--bs-success-text-emphasis,var(--bs-success,#198754));background:var(--bs-success-bg-subtle,transparent);border:1px solid currentColor;font-size:.62rem;font-weight:600;white-space:nowrap}'
            + '.health-probe-events{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.3rem}'
            + '.health-probe-event-stat{display:flex;align-items:baseline;justify-content:space-between;gap:.25rem;min-width:0}'
            + '.health-probe-event-label{font-size:.6rem;white-space:nowrap}'
            + '.health-probe-event-value{font-size:.68rem;font-weight:600;white-space:nowrap;font-variant-numeric:tabular-nums;overflow:hidden;text-overflow:ellipsis}'
            + '.health-probe-history{display:flex;flex-direction:column;gap:.34rem;min-width:0}'
            + '.health-probe-history-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;min-width:0}'
            + '.health-probe-history-title{font-size:.72rem;font-weight:600;white-space:nowrap}'
            + '.health-probe-history-buttons .btn{padding:.1rem .35rem;font-size:.64rem;line-height:1.35;touch-action:manipulation}'
            + '.health-probe-chart-wrap{position:relative;height:68px;min-height:68px;border:1px solid var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18));border-radius:.3rem;background:var(--bs-tertiary-bg,rgba(var(--bs-body-color-rgb,33,37,41),.05));overflow:hidden;color:var(--bs-success-text-emphasis,var(--bs-success,#198754))}'
            + '.health-probe-chart{display:block;width:100%;height:100%}'
            + '.health-probe-chart-grid{stroke:var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18));stroke-width:.55;vector-effect:non-scaling-stroke}'
            + '.health-probe-chart-line{stroke:currentColor;stroke-width:1.35;stroke-linecap:round;stroke-linejoin:round;vector-effect:non-scaling-stroke}'
            + '.health-probe-chart-point{fill:currentColor;stroke:var(--bs-body-bg,#fff);stroke-width:.45;vector-effect:non-scaling-stroke}'
            + '.health-probe-chart-scale{position:absolute;top:.15rem;right:.28rem;font-size:.56rem;line-height:1;color:var(--bs-secondary-color,#6c757d)!important;background:var(--bs-body-bg,#fff);padding:.08rem .15rem;border-radius:.15rem}'
            + '.health-probe-chart-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.7rem;pointer-events:none}'
            + '.health-probe-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.3rem}'
            + '.health-probe-stat{display:flex;align-items:baseline;justify-content:space-between;gap:.25rem;padding:.22rem .32rem;border-radius:.25rem;background:var(--bs-tertiary-bg,rgba(var(--bs-body-color-rgb,33,37,41),.05));color:var(--bs-body-color,#212529);font-variant-numeric:tabular-nums;min-width:0}'
            + '.health-probe-stat-label{font-size:.62rem;white-space:nowrap}'
            + '.health-probe-stat-value{font-size:.7rem;font-weight:600;white-space:nowrap}'
            + '.health-probe-route{font-size:.7rem;overflow-wrap:anywhere}'
            + '.health-probe-meta{display:flex;justify-content:space-between;gap:.6rem;margin-top:auto;font-size:.64rem;line-height:1.3;flex-wrap:wrap}'
            + '.health-probe-refresh-trigger:disabled{opacity:.7}'
            + '@media (min-width:768px){.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-message,.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-baseline,.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-route,.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-meta{display:none}.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-card-body{gap:.4rem}.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-chart-wrap{height:62px;min-height:62px}.dashboard-grid>.health-probe-card[data-widget-height="2"] .health-probe-card-body{gap:.52rem}.dashboard-grid>.health-probe-card[data-widget-height="2"] .health-probe-chart-wrap{height:170px;min-height:170px}}'
            + '@media (max-width:767.98px){.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-message,.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-baseline,.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-route,.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-meta{display:flex}.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-message,.dashboard-grid>.health-probe-card[data-widget-height="1"] .health-probe-route{display:block}.health-probe-card .health-probe-card-body{overflow-y:visible}.health-probe-chart-wrap{height:78px;min-height:78px}.health-probe-history-head{flex-wrap:wrap}.health-probe-history-buttons .btn{min-height:34px;padding:.25rem .5rem;font-size:.7rem}.health-probe-meta{margin-top:.1rem}}'
            + '@media (max-width:575.98px){.health-probe-latency-value{font-size:1.55rem}.health-probe-summary{gap:.5rem}.health-probe-quality-row{gap:.35rem}.health-probe-stat{padding:.2rem .25rem}.health-probe-stat-value{font-size:.66rem}.health-probe-history-title{font-size:.7rem}}'
            + '@media (max-width:359.98px){.health-probe-summary{align-items:flex-start}.health-probe-state{font-size:.94rem}.health-probe-latency-value{font-size:1.4rem}.health-probe-quality-row{align-items:flex-start;flex-wrap:wrap}.health-probe-quality-value{width:100%;justify-content:flex-start}.health-probe-events{grid-template-columns:minmax(0,1fr)}.health-probe-stats{gap:.18rem}.health-probe-stat{display:block;text-align:center}.health-probe-stat-label,.health-probe-stat-value{display:block}.health-probe-history-buttons .btn{min-height:36px;padding:.28rem .48rem}}'
            + '@media (prefers-reduced-motion:reduce){.health-probe-refresh-trigger .fa-spin{animation:none!important}}';
        $('<style>').attr('id', 'v118e-health-probe-styles').text(css).appendTo('head');
    }

    function option(value, label, selected) {
        return $('<option>').val(value).text(label).prop('selected', selected === true);
    }

    function sizeFields(prefix) {
        var $row = $('<div>').addClass('row g-2');
        var $width = $('<select>').addClass('form-select ' + prefix + 'HealthProbeWidth')
            .append(option('1', '1列', true), option('2', '2列'), option('3', '3列'), option('4', '全幅'));
        var $height = $('<select>').addClass('form-select ' + prefix + 'HealthProbeHeight')
            .append(option('1', '標準', true), option('2', '縦2段'));
        var $style = $('<select>').addClass('form-select ' + prefix + 'HealthProbeStyle')
            .append(
                option('info', 'info', true), option('primary', 'primary'), option('success', 'success'),
                option('secondary', 'secondary'), option('warning', 'warning'), option('danger', 'danger'), option('dark', 'dark')
            );
        $row.append(
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('横幅'), $width),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('縦幅'), $height),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('見出し色'), $style)
        );
        return $row;
    }

    function makeModal(id, formId, title, prefix, editing) {
        var titleId = id + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({
            id: id,
            tabindex: '-1',
            'aria-labelledby': titleId,
            'aria-hidden': 'true'
        });
        var $dialog = $('<div>').addClass('modal-dialog modal-dialog-centered');
        var $content = $('<div>').addClass('modal-content');
        var $form = $('<form>').attr('id', formId);
        var $header = $('<div>').addClass('modal-header')
            .append(
                $('<h5>').addClass('modal-title').attr('id', titleId)
                    .append($('<i>').addClass('fas fa-heartbeat me-2').attr('aria-hidden', 'true'), document.createTextNode(title))
            )
            .append($('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close'));
        var $body = $('<div>').addClass('modal-body')
            .append(
                $('<p>').addClass('small text-muted').text(
                    'ブラウザからこのRSS Readerまでの到達性と応答時間を約5秒ごとに確認します。2回連続で到達出来ない場合にOfflineと判定し、切断・復旧情報とLatency履歴はこのページ内だけで保持してDBへ保存しません。'
                ),
                sizeFields(prefix),
                $('<div>').addClass('form-text mt-2').text(
                    'PCでは「標準」は主要情報をコンパクトに表示し、「縦2段」はBaseline・経路・端末判定などの詳細と大きなグラフを表示します。スマートフォンでは縦幅設定に関係なく詳細を表示します。'
                )
            );
        if (editing) {
            $body.prepend($('<input>').attr({type: 'hidden'}).addClass('changeHealthProbeWidgetId'));
        } else {
            $body.prepend($('<input>').attr({type: 'hidden'}).addClass('registerHealthProbeLocation'));
        }
        var $footer = $('<div>').addClass('modal-footer');
        if (editing) {
            $footer.append(
                $('<button>').attr({type: 'button'}).addClass('btn btn-outline-danger me-auto delete-health-probe-widget').text('削除')
            );
        }
        $footer.append(
            $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる'),
            $('<button>').attr({type: 'submit'}).addClass('btn btn-primary').text(editing ? '保存' : '追加')
        );
        $form.append($header, $body, $footer);
        $content.append($form);
        $dialog.append($content);
        return $modal.append($dialog);
    }

    function addModals() {
        if ($('#registerHealthProbeWidget').length === 0) {
            $('body').append(makeModal(
                'registerHealthProbeWidget',
                'registerHealthProbeWidgetForm',
                'Connection Monitorを追加',
                'register',
                false
            ));
        }
        if ($('#changeHealthProbeWidget').length === 0) {
            $('body').append(makeModal(
                'changeHealthProbeWidget',
                'changeHealthProbeWidgetForm',
                'Connection Monitorを編集',
                'change',
                true
            ));
        }
        var location = currentLocation();
        if (location !== null) {
            $('.registerHealthProbeLocation').val(String(location));
        }
    }

    function addCatalogTile() {
        var $grid = $('#widgetCatalog-information .widget-catalog-grid').first();
        if ($grid.length === 0 || $grid.find('[data-drawer-modal-target="#registerHealthProbeWidget"]').length > 0) {
            return;
        }
        var $button = $('<button>')
            .attr({type: 'button', 'data-drawer-modal-target': '#registerHealthProbeWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
            .append($('<span>').addClass('drawer-item-icon')
                .append($('<i>').addClass('fas fa-heartbeat fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Connection Monitor'));
        if (currentLocation() === null) {
            $button.prop('disabled', true).attr('title', 'Dashboardタブで追加できます');
        }
        $grid.append($button);
    }

    function safeStyle(value) {
        value = String(value || 'info');
        return /^(?:success|primary|info|secondary|dark|warning|danger)$/.test(value) ? value : 'info';
    }

    function makeCard(widget) {
        var id = Number(widget.widget_id || 0);
        var style = safeStyle(widget.widget_style);
        var $card = $('<section>')
            .addClass(widthClass(widget.widget_width) + ' dashboard-widget information-widget-card health-probe-card')
            .attr({
                'data-dashboard-widget-id': String(id),
                'data-dashboard-widget-type': 'health_probe',
                'data-dashboard-widget-location': String(widget.widget_location),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order),
                'data-widget-width': String(widget.widget_width),
                'data-widget-height': String(widget.widget_height),
                role: 'region',
                'aria-labelledby': 'health-probe-title-' + id,
                'aria-busy': 'true'
            })
            .data('health-probe-widget', widget)
            .data('health-probe-history-window', defaultHistoryWindowMs);

        var $inner = $('<div>').addClass('health-probe-card-inner information-widget-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' health-probe-card-header information-widget-header').appendTo($inner);
        $('<button>')
            .attr({
                type: 'button',
                draggable: 'false',
                'aria-describedby': 'widget-sort-help',
                'aria-label': 'このWidgetを並び替え',
                'aria-pressed': 'false',
                title: 'ここを掴んで並び替え'
            })
            .addClass('btn btn-link widget-drag-handle')
            .append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true'))
            .appendTo($header);
        $('<small>')
            .addClass('health-probe-card-title widget-title-text information-widget-title')
            .attr('id', 'health-probe-title-' + id)
            .text('Connection Monitor')
            .appendTo($header);
        $('<button>')
            .attr({
                type: 'button',
                'aria-label': 'Connection Monitorを編集',
                'data-bs-toggle': 'modal',
                'data-bs-target': '#changeHealthProbeWidget'
            })
            .addClass('btn btn-link health-probe-edit-trigger information-widget-action')
            .append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true'))
            .appendTo($header);
        $('<button>')
            .attr({type: 'button', 'aria-label': '接続状態を再確認', title: '接続状態を再確認'})
            .addClass('btn btn-link health-probe-refresh-trigger information-widget-action')
            .append($('<i>').addClass('fas fa-sync-alt').attr('aria-hidden', 'true'))
            .appendTo($header);
        $('<div>')
            .addClass('health-probe-card-body information-widget-body')
            .attr('aria-live', 'polite')
            .appendTo($inner);

        renderCard($card, lastProbeResult || {state: 'checking', browserOnline: browserOnlineHint()});
        return $card;
    }

    function loadWidgets() {
        var location = currentLocation();
        if (location === null) {
            return;
        }
        common.apiRequest('widget.list', {widget_location: String(location)}, 5000)
            .done(function (response) {
                var result = common.responseData(response);
                var widgets = result && $.isArray(result.widgets) ? result.widgets : [];
                widgets.forEach(function (widget) {
                    if (String(widget.widget_type || '') !== 'health_probe') {
                        return;
                    }
                    if ($('[data-dashboard-widget-id="' + String(widget.widget_id) + '"]').length > 0) {
                        return;
                    }
                    common.insertCard(makeCard(widget));
                });
                if (healthProbeCardsExist()) {
                    probeNow();
                }
            });
    }

    function payload(prefix) {
        return {
            widget_style: $('.' + prefix + 'HealthProbeStyle').val(),
            widget_width: $('.' + prefix + 'HealthProbeWidth').val(),
            widget_height: $('.' + prefix + 'HealthProbeHeight').val()
        };
    }

    function bindEvents() {
        $(document)
            .off('click' + namespace, '[data-drawer-modal-target="#registerHealthProbeWidget"]')
            .on('click' + namespace, '[data-drawer-modal-target="#registerHealthProbeWidget"]', function () {
                var location = currentLocation();
                if (location !== null) {
                    $('.registerHealthProbeLocation').val(String(location));
                }
            })
            .off('submit' + namespace, '#registerHealthProbeWidgetForm')
            .on('submit' + namespace, '#registerHealthProbeWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('register');
                data.widget_location = $('.registerHealthProbeLocation').val();
                common.submitReload($(this), 'widget.healthprobe.create', data, 6000);
            })
            .off('click' + namespace, '.health-probe-edit-trigger')
            .on('click' + namespace, '.health-probe-edit-trigger', function () {
                var $card = $(this).closest('.health-probe-card');
                var widget = $card.data('health-probe-widget') || {};
                $('.changeHealthProbeWidgetId').val(String(widget.widget_id || $card.attr('data-dashboard-widget-id') || ''));
                $('.changeHealthProbeStyle').val(safeStyle(widget.widget_style || 'info'));
                $('.changeHealthProbeWidth').val(String(widget.widget_width || $card.attr('data-widget-width') || '1'));
                $('.changeHealthProbeHeight').val(String(widget.widget_height || $card.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeHealthProbeWidgetForm')
            .on('submit' + namespace, '#changeHealthProbeWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('change');
                data.widget_id = $('.changeHealthProbeWidgetId').val();
                common.submitReload($(this), 'widget.healthprobe.update', data, 6000);
            })
            .off('click' + namespace, '.delete-health-probe-widget')
            .on('click' + namespace, '.delete-health-probe-widget', function () {
                var widgetId = String($('.changeHealthProbeWidgetId').val() || '');
                var $button = $(this);
                if (!/^\d+$/.test(widgetId)
                    || !window.confirm('このConnection Monitorを削除しますか？')
                    || $button.data('request-pending') === true) {
                    return;
                }
                $button.data('request-pending', true).prop('disabled', true);
                common.apiRequest('widget.healthprobe.delete', {widget_id: widgetId}, 5000)
                    .done(function (response) {
                        if (common.responseData(response)) {
                            window.location.reload();
                        } else {
                            common.showNotice('Connection Monitorを削除出来ませんでした', 'danger');
                        }
                    })
                    .fail(function (xhr, status) {
                        common.showNotice(common.errorMessage(xhr, status), 'danger');
                    })
                    .always(function () {
                        $button.data('request-pending', false).prop('disabled', false);
                    });
            })
            .off('click' + namespace, '.health-probe-refresh-trigger')
            .on('click' + namespace, '.health-probe-refresh-trigger', function () {
                probeNow();
            })
            .off('click' + namespace, '.health-probe-window-trigger')
            .on('click' + namespace, '.health-probe-window-trigger', function () {
                var $button = $(this);
                var $card = $button.closest('.health-probe-card');
                if ($card.length === 0) {
                    return;
                }
                $card.data('health-probe-history-window', normalizeHistoryWindow($button.attr('data-health-probe-window-ms')));
                renderCard($card, lastProbeResult || {state: 'checking', browserOnline: browserOnlineHint()});
            });

        $(document)
            .off('visibilitychange' + namespace)
            .on('visibilitychange' + namespace, function () {
                if (document.hidden) {
                    clearProbeTimer();
                    clearDowntimeTimer();
                    return;
                }
                syncDowntimeTimer();
                probeNow();
            });

        $(window)
            .off('online' + namespace + ' offline' + namespace)
            .on('online' + namespace + ' offline' + namespace, function () {
                if (!document.hidden) {
                    probeNow();
                }
            });
    }

    function init() {
        common.installStyles();
        addStyles();
        addModals();
        addCatalogTile();
        bindEvents();
        loadWidgets();
        if (healthProbeCardsExist()) {
            probeNow();
        }
    }

    window.iGuguruHealthProbeWidget = {
        probeNow: probeNow
    };

    $(init);
}(jQuery, window, document));
