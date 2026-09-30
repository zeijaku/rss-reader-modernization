/* Maze Chase: original relay maze, diamond courier and triangular sentries. */
(function (window,document) {
    'use strict';
    var MAZE = [
        '###############',
        '#.....#.......#',
        '#.###.#.###.#.#',
        '#o#...#...#.#.#',
        '#.#.#####.#.#.#',
        '#...#.....#...#',
        '###.#.###.###.#',
        '#...#...#.....#',
        '#.#####.#####.#',
        '#.....#.......#',
        '#.###.#####.#.#',
        '#...o.......#o#',
        '###############'
    ];
    var WIDTH = 15, HEIGHT = 13, PLAYER = 16, ENEMIES = [28,176], TICK_MS = 150;
    var DIRECTIONS = {up:-WIDTH,left:-1,down:WIDTH,right:1};
    function walkable(index) { return index >= 0 && index < WIDTH*HEIGHT && MAZE[Math.floor(index/WIDTH)][index%WIDTH] !== '#'; }
    function nextCell(index,direction) {
        if (!Object.prototype.hasOwnProperty.call(DIRECTIONS,direction)) return index;
        var next = index+DIRECTIONS[direction];
        if ((direction === 'left' || direction === 'right') && Math.floor(next/WIDTH) !== Math.floor(index/WIDTH)) return index;
        return walkable(next) ? next : index;
    }
    function neighbours(index) { return Object.keys(DIRECTIONS).map(function (dir) { return nextCell(index,dir); }).filter(function (value) { return value !== index; }); }
    function distanceMap(origin) {
        var distances = new Array(WIDTH*HEIGHT).fill(-1), queue = [origin]; distances[origin] = 0;
        for (var i=0;i<queue.length;i++) neighbours(queue[i]).forEach(function (cell) { if (distances[cell] === -1) { distances[cell] = distances[queue[i]]+1; queue.push(cell); } });
        return distances;
    }
    function createState() {
        var items = MAZE.join('').split('').map(function (value) { return value === '.' ? 1 : value === 'o' ? 2 : 0; }); items[PLAYER] = 0;
        return {player:PLAYER,direction:null,wanted:null,enemies:ENEMIES.slice(),items:items,remaining:items.filter(Boolean).length,score:0,power:0,ticks:0,status:'ready'};
    }
    function collect(state) {
        var item = state.items[state.player];
        if (item) { state.items[state.player] = 0; state.remaining--; state.score += item === 2 ? 50 : 10; if (item === 2) state.power = 36; }
    }
    function collide(state,enemyIndex) {
        if (state.power > 0) { state.enemies[enemyIndex] = ENEMIES[enemyIndex]; state.score += 100; }
        else state.status = 'gameover';
    }
    function advance(state,random) {
        if (state.status !== 'playing') return state;
        random = random || Math.random; state.ticks++; if (state.power > 0) state.power--;
        var previousPlayer = state.player;
        if (state.wanted && nextCell(state.player,state.wanted) !== state.player) state.direction = state.wanted;
        if (state.direction) state.player = nextCell(state.player,state.direction);
        collect(state);
        var previousEnemies = state.enemies.slice();
        for (var e=0;e<state.enemies.length;e++) if (state.enemies[e] === state.player) collide(state,e);
        if (state.status !== 'playing') return state;
        if (state.remaining === 0) { state.status = 'cleared'; return state; }
        if (state.ticks % 3 === 0) {
            var distances = distanceMap(state.player);
            state.enemies = state.enemies.map(function (enemy,index) {
                var options = neighbours(enemy);
                if (!options.length) return enemy;
                if (!state.power && index === 1 && state.ticks % 18 < 9) return options[Math.min(options.length-1,Math.floor(Math.max(0,random())*options.length))];
                options.sort(function (a,b) { return state.power ? distances[b]-distances[a] : distances[a]-distances[b]; });
                return options[0];
            });
            for (var j=0;j<state.enemies.length;j++) {
                if (state.enemies[j] === state.player || (previousEnemies[j] === state.player && state.enemies[j] === previousPlayer)) collide(state,j);
                if (state.status !== 'playing') break;
            }
        }
        return state;
    }
    function keyDirection(key) { return ({ArrowUp:'up',ArrowLeft:'left',ArrowDown:'down',ArrowRight:'right',w:'up',a:'left',s:'down',d:'right',W:'up',A:'left',S:'down',D:'right'})[key] || null; }
    function mount(context) {
        var state = createState(), userPaused = false, suspended = false, frameId = null, lastTime = 0, accumulated = 0, destroyed = false;
        var wrap = document.createElement('div'); wrap.className = 'maze-chase-board-wrap';
        var canvas = document.createElement('canvas'); canvas.className = 'maze-chase-board'; canvas.tabIndex = 0;
        canvas.setAttribute('role','img'); canvas.setAttribute('aria-label','Maze Chase。矢印キーまたはWASDで移動。Pauseボタンで一時停止。');
        wrap.appendChild(canvas); context.stage.appendChild(wrap);
        var pad = document.createElement('div'); pad.className = 'game-widget-dpad'; pad.setAttribute('role','group'); pad.setAttribute('aria-label','移動方向');
        [['up','↑','上'],['left','←','左'],['down','↓','下'],['right','→','右']].forEach(function (entry) {
            var button = document.createElement('button'); button.type = 'button'; button.className = 'btn btn-outline-secondary game-widget-direction';
            button.textContent = entry[1]; button.setAttribute('data-game-direction',entry[0]); button.setAttribute('aria-label',entry[2]+'へ移動');
            context.on(button,'click',function () { if (state.status === 'playing' && !userPaused && !suspended) state.wanted = entry[0]; }); pad.appendChild(button);
        });
        context.stage.appendChild(pad);
        var help = document.createElement('p'); help.className = 'game-widget-help text-muted';
        help.textContent = '矢印／WASD／方向ボタンで移動。小さな粒を集め、三角の敵を避けます。大きな菱形を取ると短時間だけ敵を押し戻せます。すべて集めるとCLEAR。'; context.stage.appendChild(help);
        var ctx = canvas.getContext('2d'); if (!ctx) throw new Error('Canvas unavailable');
        var cell = 20, cssWidth = 300;
        function draw() {
            var style = window.getComputedStyle ? window.getComputedStyle(canvas) : null;
            var color = function (name,fallback) { return style ? style.getPropertyValue(name).trim() || fallback : fallback; };
            ctx.clearRect(0,0,cssWidth,cell*HEIGHT);
            ctx.fillStyle = color('--bs-body-bg','#fff'); ctx.fillRect(0,0,cssWidth,cell*HEIGHT);
            for (var i=0;i<WIDTH*HEIGHT;i++) {
                var x = (i%WIDTH)*cell, y = Math.floor(i/WIDTH)*cell;
                if (!walkable(i)) { ctx.fillStyle = color('--bs-secondary-color','#6c757d'); ctx.fillRect(x+1,y+1,cell-2,cell-2); }
                else if (state.items[i]) {
                    ctx.fillStyle = color('--bs-warning','#c78b16');
                    var size = state.items[i] === 2 ? cell*.3 : cell*.1;
                    if (state.items[i] === 2) { ctx.beginPath(); ctx.moveTo(x+cell/2,y+cell/2-size);ctx.lineTo(x+cell/2+size,y+cell/2);ctx.lineTo(x+cell/2,y+cell/2+size);ctx.lineTo(x+cell/2-size,y+cell/2);ctx.closePath();ctx.fill(); }
                    else ctx.fillRect(x+cell/2-size,y+cell/2-size,size*2,size*2);
                }
            }
            function diamond(index,colorValue) {
                var x=(index%WIDTH+.5)*cell,y=(Math.floor(index/WIDTH)+.5)*cell,r=cell*.34;
                ctx.fillStyle=colorValue;ctx.beginPath();ctx.moveTo(x,y-r);ctx.lineTo(x+r,y);ctx.lineTo(x,y+r);ctx.lineTo(x-r,y);ctx.closePath();ctx.fill();
            }
            diamond(state.player,color('--bs-primary','#0d6efd'));
            state.enemies.forEach(function (index) {
                var x=(index%WIDTH+.5)*cell,y=(Math.floor(index/WIDTH)+.5)*cell,r=cell*.34;
                ctx.fillStyle=state.power ? color('--bs-info','#1599a5') : color('--bs-danger','#c63555');ctx.beginPath();ctx.moveTo(x,y-r);ctx.lineTo(x+r,y+r);ctx.lineTo(x-r,y+r);ctx.closePath();ctx.fill();
            });
        }
        function resize() {
            cssWidth = Math.max(150,Math.min(540,wrap.clientWidth || 300)); cell = cssWidth/WIDTH;
            var ratio = Math.max(1,Math.min(2,window.devicePixelRatio || 1));
            canvas.width = Math.round(cssWidth*ratio); canvas.height = Math.round(cell*HEIGHT*ratio);
            canvas.style.width = cssWidth+'px'; canvas.style.height = (cell*HEIGHT)+'px'; ctx.setTransform(ratio,0,0,ratio,0,0); draw();
        }
        function render() {
            var paused = userPaused || suspended;
            var message = state.status === 'ready' ? 'Startで開始します。' : state.status === 'cleared' ? 'CLEAR！すべてのItemを集めました。' : state.status === 'gameover' ? 'GAME OVER。Restartで再挑戦できます。' : paused ? (userPaused ? 'Pause中です。Resumeで再開します。' : '非表示またはModal表示のため停止中です。') : '残り '+state.remaining+'個'+(state.power ? '／Escape Item有効' : '');
            context.update({score:state.score,message:message,started:state.status !== 'ready',playing:state.status === 'playing',paused:paused,status:state.status === 'playing' && paused ? 'paused' : state.status}); draw();
        }
        function stop() { if (frameId !== null) window.cancelAnimationFrame(frameId); frameId=null; lastTime=0; accumulated=0; }
        function frame(time) {
            frameId=null;
            if (destroyed || userPaused || suspended || state.status !== 'playing') return;
            if (lastTime) accumulated += Math.min(250,Math.max(0,time-lastTime)); lastTime=time;
            if (accumulated >= TICK_MS) { accumulated-=TICK_MS; advance(state); render(); }
            if (state.status === 'playing') frameId=window.requestAnimationFrame(frame);
        }
        function startLoop() { if (!destroyed && !userPaused && !suspended && state.status === 'playing' && frameId === null) frameId=window.requestAnimationFrame(frame); }
        context.on(canvas,'keydown',function (event) {
            var direction=keyDirection(event.key);
            if (!direction || event.altKey || event.ctrlKey || event.metaKey || state.status !== 'playing' || userPaused || suspended) return;
            event.preventDefault(); event.stopPropagation(); state.wanted=direction;
        });
        context.on(window,'resize',resize);
        var resizeObserver = window.ResizeObserver ? new window.ResizeObserver(resize) : null; if (resizeObserver) resizeObserver.observe(wrap);
        resize(); render();
        return {
            restart:function () { stop(); state=createState();state.status='playing';userPaused=false;render();canvas.focus({preventScroll:true});startLoop(); },
            togglePause:function () { if (state.status !== 'playing') return;userPaused=!userPaused;stop();render();startLoop(); },
            setSuspended:function (value) { if (suspended === value) return;suspended=value;stop();render();startLoop(); },
            resize:resize,
            destroy:function () { destroyed=true;stop();if (resizeObserver) resizeObserver.disconnect(); }
        };
    }
    window.RssMazeChase = {maze:MAZE.slice(),width:WIDTH,height:HEIGHT,playerStart:PLAYER,enemyStarts:ENEMIES.slice(),createState:createState,nextCell:nextCell,neighbours:neighbours,distanceMap:distanceMap,advance:advance,keyDirection:keyDirection};
    window.RssGameWidget.register('maze_chase',mount);
})(window,document);
