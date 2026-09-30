/* Falling Blocks: local 10 x 18 board and seven geometric pieces. */
(function (window, document) {
    'use strict';
    var WIDTH = 10, HEIGHT = 18;
    var SHAPES = [
        [[0,0,0,0],[1,1,1,1],[0,0,0,0],[0,0,0,0]],
        [[1,1],[1,1]],
        [[0,1,0],[1,1,1],[0,0,0]],
        [[1,0,0],[1,1,1],[0,0,0]],
        [[0,0,1],[1,1,1],[0,0,0]],
        [[0,1,1],[1,1,0],[0,0,0]],
        [[1,1,0],[0,1,1],[0,0,0]]
    ];
    var NAMES = ['Bar','Square','Branch','Hook','Corner','Step','Zigzag'];
    var COLORS = ['#267d9c','#be7429','#8566bd','#397fba','#c05e68','#45855b','#a07940'];
    function bag(random) {
        var result = [0,1,2,3,4,5,6];
        for (var i = 6; i > 0; i--) {
            var value = Number(random()), j = Math.floor((Number.isFinite(value) ? Math.max(0,Math.min(.999999,value)) : 0) * (i+1));
            var swap = result[i]; result[i] = result[j]; result[j] = swap;
        }
        return result;
    }
    function take(state) {
        if (!state.queue.length) state.queue = bag(state.random);
        return state.queue.shift();
    }
    function fits(state, matrix, x, y) {
        for (var r=0;r<matrix.length;r++) for (var c=0;c<matrix[r].length;c++) if (matrix[r][c]) {
            var bx=x+c, by=y+r;
            if (bx<0 || bx>=WIDTH || by<0 || by>=HEIGHT || state.board[by][bx]) return false;
        }
        return true;
    }
    function spawn(state) {
        var type=state.next; state.next=take(state);
        var matrix=SHAPES[type].map(function (row) { return row.slice(); });
        state.active={type:type,matrix:matrix,x:Math.floor((WIDTH-matrix.length)/2),y:0};
        if (!fits(state,matrix,state.active.x,0)) state.status='gameover';
    }
    function createState(random) {
        var state={board:Array.from({length:HEIGHT},function () { return new Array(WIDTH).fill(0); }),queue:[],random:random || Math.random,next:0,active:null,score:0,lines:0,level:1,status:'ready'};
        state.next=take(state); spawn(state); return state;
    }
    function move(state, dx) {
        if (state.status!=='playing' || (dx!==-1 && dx!==1)) return false;
        var p=state.active;
        if (!fits(state,p.matrix,p.x+dx,p.y)) return false;
        p.x+=dx; return true;
    }
    function rotate(state, clockwise) {
        if (state.status!=='playing') return false;
        var p=state.active, size=p.matrix.length;
        var matrix=Array.from({length:size},function (_,r) { return Array.from({length:size},function (_,c) { return clockwise===false ? p.matrix[c][size-1-r] : p.matrix[size-1-c][r]; }); });
        // Small local wall/floor offsets; no external rotation system.
        var offsets=[[0,0],[-1,0],[1,0],[-2,0],[2,0],[0,-1]];
        for (var i=0;i<offsets.length;i++) {
            var x=p.x+offsets[i][0], y=p.y+offsets[i][1];
            if (fits(state,matrix,x,y)) { p.matrix=matrix;p.x=x;p.y=y;return true; }
        }
        return false;
    }
    function lock(state) {
        if (state.status!=='playing') return;
        var p=state.active;
        for (var r=0;r<p.matrix.length;r++) for (var c=0;c<p.matrix[r].length;c++) if (p.matrix[r][c]) state.board[p.y+r][p.x+c]=p.type+1;
        var remaining=state.board.filter(function (row) { return row.some(function (value) { return !value; }); });
        var cleared=HEIGHT-remaining.length;
        while (remaining.length<HEIGHT) remaining.unshift(new Array(WIDTH).fill(0));
        state.board=remaining;
        state.score+=([0,100,260,460,720][cleared] || 0)*state.level;
        state.lines+=cleared; state.level=1+Math.floor(state.lines/8); spawn(state);
    }
    function drop(state, soft) {
        if (state.status!=='playing') return false;
        var p=state.active;
        if (fits(state,p.matrix,p.x,p.y+1)) { p.y++; if (soft) state.score++; return true; }
        lock(state); return false;
    }
    function hardDrop(state) {
        if (state.status!=='playing') return 0;
        var p=state.active, distance=0;
        while (fits(state,p.matrix,p.x,p.y+1)) { p.y++;distance++; }
        state.score+=distance*2;lock(state);return distance;
    }
    function gravity(state) { return Math.max(90,800-(state.level-1)*55); }
    function keyAction(key) { var keys={ArrowLeft:'left',ArrowRight:'right',ArrowUp:'rotate',ArrowDown:'soft',x:'rotate',X:'rotate',z:'counter',Z:'counter',' ':'hard'};return Object.prototype.hasOwnProperty.call(keys,key) ? keys[key] : null; }
    function mount(context) {
        var state=createState(), userPaused=false, suspended=false, destroyed=false, frameId=null, lastTime=0, accumulated=0;
        var wrap=document.createElement('div');wrap.className='falling-blocks-board-wrap';
        var info=document.createElement('p');info.className='falling-blocks-info text-muted';
        var canvas=document.createElement('canvas');canvas.className='falling-blocks-board';canvas.tabIndex=0;
        canvas.setAttribute('role','img');canvas.setAttribute('aria-label','Falling Blocks。左右キーで移動、上で回転、下でSoft Drop、SpaceでHard Drop。');
        wrap.append(info,canvas);context.stage.appendChild(wrap);
        var controls=document.createElement('div');controls.className='falling-blocks-actions';controls.setAttribute('role','group');controls.setAttribute('aria-label','Block操作');
        [['left','←','左へ移動'],['right','→','右へ移動'],['rotate','↻','右回転'],['counter','↺','左回転'],['soft','↓','Soft Drop'],['hard','⇓','Hard Drop']].forEach(function (entry) {
            var button=document.createElement('button');button.type='button';button.className='btn btn-sm btn-outline-secondary falling-blocks-action';button.textContent=entry[1];button.setAttribute('data-block-action',entry[0]);button.setAttribute('aria-label',entry[2]);
            context.on(button,'click',function () { act(entry[0]);canvas.focus({preventScroll:true}); });controls.appendChild(button);
        });context.stage.appendChild(controls);
        var help=document.createElement('p');help.className='game-widget-help text-muted';help.textContent='左右で移動／上・Xで右回転／Zで左回転／下でSoft Drop／SpaceでHard Drop。横一列を埋めて消去。8 LinesごとにLevelが上がります。';context.stage.appendChild(help);
        var ctx=canvas.getContext('2d');if (!ctx) throw new Error('Canvas unavailable');
        var cell=20;
        function draw() {
            var style=window.getComputedStyle(canvas), background=style.getPropertyValue('--bs-body-bg').trim() || '#fff', grid=style.getPropertyValue('--bs-border-color').trim() || '#d6dce1';
            ctx.fillStyle=background;ctx.fillRect(0,0,cell*WIDTH,cell*HEIGHT);
            function square(x,y,type) {ctx.fillStyle=COLORS[type-1];ctx.fillRect(x*cell+1,y*cell+1,cell-2,cell-2);ctx.strokeStyle='rgba(255,255,255,.6)';ctx.strokeRect(x*cell+3,y*cell+3,cell-6,cell-6);}
            for (var r=0;r<HEIGHT;r++) for (var c=0;c<WIDTH;c++) {
                ctx.strokeStyle=grid;ctx.strokeRect(c*cell,r*cell,cell,cell);
                if (state.board[r][c]) square(c,r,state.board[r][c]);
            }
            if (state.status!=='gameover') {var p=state.active;for (var y=0;y<p.matrix.length;y++) for (var x=0;x<p.matrix[y].length;x++) if(p.matrix[y][x]) square(p.x+x,p.y+y,p.type+1);}
        }
        function resize() {
            var width=Math.max(100,Math.min(220,wrap.clientWidth || 200));cell=width/WIDTH;
            var ratio=Math.max(1,Math.min(2,window.devicePixelRatio || 1));canvas.width=Math.round(width*ratio);canvas.height=Math.round(cell*HEIGHT*ratio);canvas.style.width=width+'px';canvas.style.height=(cell*HEIGHT)+'px';ctx.setTransform(ratio,0,0,ratio,0,0);draw();
        }
        function render() {
            var paused=userPaused || suspended;
            info.textContent='Level '+state.level+' · Lines '+state.lines+' · Next '+NAMES[state.next];
            var message=state.status==='ready' ? 'Startで開始します。' : state.status==='gameover' ? 'GAME OVER。Restartで再挑戦できます。' : paused ? (userPaused ? 'Pause中です。Resumeで再開します。' : '非表示またはModal表示のため停止中です。') : '横一列を埋めて消去します。';
            context.update({score:state.score,message:message,started:state.status!=='ready',playing:state.status==='playing',paused:paused,status:state.status==='playing'&&paused ? 'paused' : state.status});draw();
        }
        function act(action) {
            if(state.status!=='playing'||userPaused||suspended||destroyed)return;
            var previous=state.active;
            if(action==='left')move(state,-1);else if(action==='right')move(state,1);else if(action==='rotate')rotate(state,true);else if(action==='counter')rotate(state,false);else if(action==='soft')drop(state,true);else if(action==='hard')hardDrop(state);
            if(previous!==state.active||action==='soft')accumulated=0;
            render();if(state.status==='gameover')stop();
        }
        function stop() {if(frameId!==null)window.cancelAnimationFrame(frameId);frameId=null;lastTime=0;accumulated=0;}
        function frame(time) {
            frameId=null;if(destroyed||userPaused||suspended||state.status!=='playing')return;
            if(lastTime)accumulated+=Math.min(250,Math.max(0,time-lastTime));lastTime=time;
            if(accumulated>=gravity(state)){accumulated-=gravity(state);drop(state,false);render();}
            if(state.status==='playing')frameId=window.requestAnimationFrame(frame);
        }
        function startLoop(){if(!destroyed&&!userPaused&&!suspended&&state.status==='playing'&&frameId===null)frameId=window.requestAnimationFrame(frame);}
        context.on(canvas,'keydown',function(event){var action=keyAction(event.key);if(!action||event.altKey||event.ctrlKey||event.metaKey||state.status!=='playing'||userPaused||suspended)return;event.preventDefault();event.stopPropagation();if(event.repeat&&(action==='hard'||action==='rotate'||action==='counter'))return;act(action);});
        context.on(window,'resize',resize);
        var resizeObserver=window.ResizeObserver ? new window.ResizeObserver(resize) : null;if(resizeObserver)resizeObserver.observe(wrap);
        resize();render();
        return {restart:function(){stop();state=createState();state.status='playing';userPaused=false;render();canvas.focus({preventScroll:true});startLoop();},togglePause:function(){if(state.status!=='playing')return;userPaused=!userPaused;stop();render();startLoop();},setSuspended:function(value){if(suspended===value)return;suspended=value;stop();render();startLoop();},resize:resize,destroy:function(){destroyed=true;stop();if(resizeObserver)resizeObserver.disconnect();}};
    }
    window.RssFallingBlocks={width:WIDTH,height:HEIGHT,shapes:SHAPES.map(function(matrix){return matrix.map(function(row){return row.slice();});}),bag:bag,createState:createState,fits:fits,move:move,rotate:rotate,lock:lock,drop:drop,hardDrop:hardDrop,gravity:gravity,keyAction:keyAction};
    window.RssGameWidget.register('falling_blocks',mount);
})(window,document);
