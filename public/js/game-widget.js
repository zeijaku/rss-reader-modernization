/* Game Widget lifecycle for new modules. Legacy game engines retain their own lifecycle. */
(function (window, document) {
    'use strict';
    var source = document.currentScript;
    var revisionMatch = source && /(?:[?&])v=([A-Za-z0-9._-]+)(?:[&#]|$)/.exec(source.src || '');
    var revision = revisionMatch ? revisionMatch[1] : '';
    var catalog = Object.assign(Object.create(null), {maze_chase: {title: 'Maze Chase', script: './js/maze-chase.js'}});
    var titles = {icon_quest:'Icon Quest', lights_out:'Lights Out', wire_defense:'Wire Defense', block_collapse:'Block Collapse', cursor_field:'Cursor Field', game_2048:'2048', reversi:'Reversi', maze_chase:'Maze Chase'};
    var factories = Object.create(null), loads = Object.create(null), records = new Map();
    var expanded = null, previousOverflow = '', observer = null;
    function assetUrl(path) { return revision ? path + '?v=' + encodeURIComponent(revision) : path; }
    function node(tag, className, value) {
        var element = document.createElement(tag);
        if (className) element.className = className;
        if (value !== undefined) element.textContent = value;
        return element;
    }
    function positive(value) { return /^[1-9][0-9]*$/.test(String(value || '')); }
    function storageKey(user, widget, game) {
        return positive(user) && positive(widget) && catalog[game] ? 'rssReader.gameWidget.v1.' + game + '.user.' + user + '.widget.' + widget : null;
    }
    function readBest(key) {
        var best = 0;
        if (!key) return best;
        ['localStorage', 'sessionStorage'].forEach(function (name) {
            try { var raw = window[name].getItem(key); if (/^[0-9]{1,9}$/.test(raw || '')) best = Math.max(best, Number(raw)); } catch (error) {}
        });
        return best;
    }
    function saveBest(record, score) {
        if (!Number.isSafeInteger(score) || score < 0 || score > 999999999) return;
        if (record.saved && score <= record.best) return;
        record.saved = true; record.best = Math.max(record.best, score);
        if (!record.key) return;
        var value = String(record.best);
        try { window.localStorage.setItem(record.key, value); record.storage = 'localStorage'; return; } catch (error) {}
        try { window.sessionStorage.setItem(record.key, value); record.storage = 'sessionStorage'; return; } catch (error) {}
        record.storage = 'memory';
    }
    function removeWidgetState(widgetId) {
        var main = document.getElementById('main-content'), user = main && main.getAttribute('data-dashboard-user-id');
        Object.keys(catalog).forEach(function (game) {
            var key = storageKey(user, widgetId, game);
            if (!key) return;
            ['localStorage','sessionStorage'].forEach(function (name) { try { window[name].removeItem(key); } catch (error) {} });
        });
    }
    function load(game) {
        if (factories[game]) return Promise.resolve(factories[game]);
        if (loads[game]) return loads[game];
        loads[game] = new Promise(function (resolve, reject) {
            var script = node('script');
            script.src = assetUrl(catalog[game].script);
            script.onload = function () {
                script.onload = script.onerror = null;
                if (factories[game]) resolve(factories[game]);
                else { delete loads[game]; reject(new Error('Game module did not register')); }
            };
            script.onerror = function () {
                script.onload = script.onerror = null;
                script.remove(); delete loads[game]; reject(new Error('Game module could not load'));
            };
            document.head.appendChild(script);
        });
        return loads[game];
    }
    function on(record, target, type, callback, options) {
        target.addEventListener(type, callback, options);
        record.cleanups.push(function () { target.removeEventListener(type, callback, options); });
    }
    function setExpanded(record, enabled) {
        if (enabled && expanded && expanded !== record) setExpanded(expanded, false);
        if (enabled) {
            expanded = record; previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden'; record.card.classList.add('game-widget-expanded');
            record.inner.setAttribute('role', 'dialog'); record.inner.setAttribute('aria-modal', 'true');
            record.inner.setAttribute('aria-label', catalog[record.game].title + ' 拡大表示');
        } else {
            record.card.classList.remove('game-widget-expanded');
            record.inner.removeAttribute('role'); record.inner.removeAttribute('aria-modal'); record.inner.removeAttribute('aria-label');
            if (expanded === record) { expanded = null; document.body.style.overflow = previousOverflow; }
        }
        record.expandButton.textContent = enabled ? '縮小' : '拡大';
        record.expandButton.setAttribute('aria-expanded', enabled ? 'true' : 'false');
        if (record.instance && record.instance.resize) record.instance.resize();
        suspend(record);
    }
    function destroy(card) {
        var record = records.get(card);
        if (!record) return;
        records.delete(card); record.destroyed = true;
        if (expanded === record) setExpanded(record, false);
        if (record.instance && record.instance.destroy) record.instance.destroy();
        record.cleanups.forEach(function (cleanup) { cleanup(); }); record.cleanups = [];
    }
    function suspend(record) {
        if (record.instance && record.instance.setSuspended) record.instance.setSuspended(document.hidden || (record.visible === false && expanded !== record) || record.modalOpen === true);
    }
    function shell(record, factory) {
        var body = record.card.querySelector('.mini-game-card-body');
        if (!body) throw new Error('Game body missing');
        body.replaceChildren(); body.classList.add('game-widget-body');
        var summary = node('div','game-widget-summary');
        var score = node('strong','game-widget-score','0'), best = node('strong','game-widget-best',String(record.best));
        var scoreLabel = node('span', '', 'Score '), bestLabel = node('span', '', 'Best ');
        scoreLabel.appendChild(score); bestLabel.appendChild(best); summary.append(scoreLabel,bestLabel);
        var stage = node('div','game-widget-stage'), controls = node('div','game-widget-controls');
        var start = node('button','btn btn-sm btn-primary game-widget-start','Start');
        var pause = node('button','btn btn-sm btn-outline-secondary game-widget-pause','Pause'); pause.disabled = true;
        var expand = node('button','btn btn-sm btn-outline-secondary game-widget-expand','拡大'); expand.setAttribute('aria-expanded','false');
        [start,pause,expand].forEach(function (button) { button.type = 'button'; });
        controls.append(start,pause,expand);
        var status = node('p','game-widget-status text-muted','Startで開始します。'); status.setAttribute('role','status'); status.setAttribute('aria-live','polite');
        var note = node('p','game-widget-storage-note text-muted','Bestをこの端末に保存します。SoundはOFFです。');
        body.append(summary,stage,controls,status,note); record.expandButton = expand;
        var context = {
            stage: stage,
            on: function (target,type,callback,options) { on(record,target,type,callback,options); },
            update: function (view) {
                score.textContent = String(view.score); saveBest(record,view.score); best.textContent = String(record.best);
                status.textContent = view.message;
                start.textContent = view.started ? 'Restart' : 'Start';
                pause.disabled = !view.playing; pause.textContent = view.paused ? 'Resume' : 'Pause';
                pause.setAttribute('aria-pressed',view.paused ? 'true' : 'false');
                note.textContent = record.storage === 'memory' ? '保存できないため、この画面内だけでBestを保持します。SoundはOFFです。' : record.storage === 'sessionStorage' ? 'BestはこのTabを閉じるまで保持します。SoundはOFFです。' : 'Bestをこの端末に保存します。SoundはOFFです。';
                record.card.setAttribute('data-game-widget-status',view.status);
            }
        };
        record.instance = factory(context);
        on(record,start,'click',function () { record.instance.restart(); });
        on(record,pause,'click',function () { record.instance.togglePause(); });
        on(record,expand,'click',function () { setExpanded(record,expanded !== record); });
        on(record,record.card,'keydown',function (event) {
            if (expanded !== record) return;
            if (event.key === 'Escape') { event.preventDefault(); setExpanded(record,false); expand.focus(); }
            if (event.key === 'Tab') {
                var buttons = Array.from(record.inner.querySelectorAll('button:not(:disabled),[tabindex="0"]')).filter(function (el) { return el.getClientRects().length; });
                var first = buttons[0], last = buttons[buttons.length-1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        on(record,document,'visibilitychange',function () { suspend(record); });
        on(record,window,'pagehide',function () { if (record.instance) record.instance.setSuspended(true); });
        on(record,window,'pageshow',function () { suspend(record); });
        if (window.IntersectionObserver) {
            var intersection = new window.IntersectionObserver(function (entries) { record.visible = entries[0].isIntersecting; suspend(record); });
            intersection.observe(record.card); record.cleanups.push(function () { intersection.disconnect(); });
        }
        suspend(record); record.card.setAttribute('data-game-widget-initialized','1');
    }
    function initCard(card) {
        var game = card.getAttribute('data-mini-game-type');
        if (!catalog[game] || records.has(card)) return;
        var main = document.getElementById('main-content'), id = card.getAttribute('data-dashboard-widget-id');
        var user = main && main.getAttribute('data-dashboard-user-id');
        if (!positive(id) || !positive(user)) return;
        var record = {card:card, game:game, key:storageKey(user,id,game), best:0, storage:'memory', cleanups:[], visible:true, destroyed:false, inner:card.querySelector('.mini-game-card-inner')};
        if (!record.inner) return;
        record.best = readBest(record.key); saveBest(record,record.best); records.set(card,record);
        card.setAttribute('data-mini-game-initialized','1');
        load(game).then(function (factory) {
            if (record.destroyed || !card.isConnected || card.getAttribute('data-mini-game-type') !== game) return;
            shell(record,factory);
        }).catch(function () {
            if (record.destroyed || !card.isConnected) return;
            var body = card.querySelector('.mini-game-card-body'); if (!body) return;
            body.replaceChildren(node('p','text-danger','Gameを読み込めませんでした。再試行してください。'));
            var retry = node('button','btn btn-sm btn-outline-primary','再試行'); retry.type = 'button'; body.appendChild(retry);
            on(record,retry,'click',function () { destroy(card); initCard(card); });
        });
    }
    function init() {
        records.forEach(function (record,card) { if (!card.isConnected || card.getAttribute('data-mini-game-type') !== record.game) destroy(card); });
        document.querySelectorAll('.mini-game-card[data-mini-game-type="maze_chase"]').forEach(initCard);
    }
    function syncTitle(event) {
        var select = event.target;
        if (!select || !select.classList || !(select.classList.contains('registerGameType') || select.classList.contains('changeGameType'))) return;
        var previous = select.getAttribute('data-game-widget-previous-type') || select.getAttribute('data-previous-game-type') || 'icon_quest';
        var title = document.querySelector(select.classList.contains('changeGameType') ? '.changeGameTitleValue' : '.registerGameTitleValue');
        if ((catalog[select.value] || catalog[previous]) && title && (!title.value.trim() || Object.values(titles).indexOf(title.value.trim()) !== -1)) title.value = titles[select.value] || title.value;
        select.setAttribute('data-game-widget-previous-type',select.value);
    }
    function start() {
        document.addEventListener('change',syncTitle,true);
        document.addEventListener('click',function (event) {
            var button = event.target.closest && event.target.closest('[data-game-preset="maze_chase"]');
            if (button) {
                var select = document.getElementById('registerGameType'), title = document.querySelector('.registerGameTitleValue');
                if (select) { select.value = 'maze_chase'; if (title) title.value = titles.maze_chase; select.dispatchEvent(new window.Event('change',{bubbles:true})); }
            }
        });
        if (window.jQuery) {
            window.jQuery(document).on('iguguru:widget-card-refreshed.rssGameWidget',init);
            window.jQuery(document).on('show.bs.modal.rssGameWidget hidden.bs.modal.rssGameWidget',function (event) {
                var open = event.type === 'show'; records.forEach(function (record) { record.modalOpen = open; suspend(record); });
            });
        }
        if (window.MutationObserver && document.body) {
            observer = new window.MutationObserver(function (mutations) {
                if (mutations.some(function (mutation) { return Array.from(mutation.addedNodes).concat(Array.from(mutation.removedNodes)).some(function (el) { return el.nodeType === 1 && (el.matches('.mini-game-card') || el.querySelector('.mini-game-card')); }); })) init();
            });
            observer.observe(document.body,{childList:true,subtree:true});
        }
        init();
    }
    window.RssGameWidget = {
        register: function (game,factory) { if (!catalog[game] || typeof factory !== 'function') throw new Error('Unsupported Game module'); factories[game] = factory; },
        init:init, destroy:destroy, removeWidgetState:removeWidgetState, storageKey:storageKey
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',start,{once:true}); else start();
})(window,document);
