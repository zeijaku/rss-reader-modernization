/* Word Tiles: original 9x9 solo board; separately loaded fixed English dictionary. */
(function(window,document){
    'use strict';
    function build(data,japanese){
    var SIZE=9, CENTER=40, SCHEMA=1, WORDLIST=1;
    if(!data||data.revision!==(japanese?1:2))throw new Error('Word dictionary unavailable');
    var WORDS=data.words;
    var DICTIONARY=new Set(WORDS), LETTERS={A:6,B:1,C:3,D:3,E:10,F:1,G:2,H:2,I:6,J:1,K:1,L:3,M:2,N:5,O:6,P:2,Q:1,R:5,S:5,T:6,U:3,V:1,W:1,X:1,Y:1,Z:1};
    // Fixed Japanese inventory: dictionary refreshes do not invalidate saved tile counts.
    if(japanese)LETTERS={"ぁ":1,"あ":2,"ぃ":1,"い":7,"ぅ":1,"う":7,"ぇ":1,"え":1,"ぉ":1,"お":2,"か":3,"が":1,"き":3,"ぎ":1,"く":4,"ぐ":1,"け":1,"げ":1,"こ":2,"ご":1,"さ":2,"ざ":1,"し":4,"じ":2,"す":2,"ず":1,"せ":1,"ぜ":1,"そ":1,"ぞ":1,"た":2,"だ":1,"ち":2,"ぢ":1,"っ":2,"つ":2,"づ":1,"て":2,"で":1,"と":2,"ど":1,"な":1,"に":1,"ぬ":1,"ね":1,"の":1,"は":1,"ば":1,"ぱ":1,"ひ":1,"び":1,"ぴ":1,"ふ":1,"ぶ":1,"ぷ":1,"へ":1,"べ":1,"ぺ":1,"ほ":1,"ぼ":1,"ぽ":1,"ま":2,"み":1,"む":1,"め":1,"も":1,"ゃ":1,"や":1,"ゅ":2,"ゆ":1,"ょ":2,"よ":1,"ら":2,"り":3,"る":2,"れ":1,"ろ":1,"わ":1,"を":1,"ん":8,"ゔ":1,"ー":3};
    function normalize(value){var text=String(value).normalize('NFKC').trim();return japanese?Array.from(text).map(function(c){return c>='ァ'&&c<='ヶ'?String.fromCharCode(c.charCodeAt(0)-96):c;}).join(''):text.toUpperCase();}
    function validLetter(value,empty){return typeof value==='string'&&((empty&&value==='')||(value.length===1&&Object.prototype.hasOwnProperty.call(LETTERS,value)));}
    var BAG=Object.keys(LETTERS).reduce(function(out,key){return out.concat(new Array(LETTERS[key]).fill(key));},[]);
    function shuffle(array,random){for(var i=array.length-1;i>0;i--){var value=Number((random||Math.random)()),j=Math.floor((Number.isFinite(value)?Math.max(0,Math.min(.999999,value)):0)*(i+1));var old=array[i];array[i]=array[j];array[j]=old;}return array;}
    function createState(random){var rack=japanese?['さ','く','ら','ね','こ','や','ま']:['C','A','T','R','E','S','O'],pool=BAG.slice();rack.forEach(function(letter){pool.splice(pool.indexOf(letter),1);});var created={schema:SCHEMA,wordlist:WORDLIST,board:new Array(SIZE*SIZE).fill(''),rack:rack,pool:shuffle(pool,random),pending:[],score:0,turns:0,status:'ready'};if(japanese)created.language='ja';return created;}
    function active(state){return state.status==='playing';}
    function place(state,index,rackIndex){
        if(!active(state)||!Number.isInteger(index)||index<0||index>=81||!Number.isInteger(rackIndex)||rackIndex<0||rackIndex>=7||!state.rack[rackIndex]||state.board[index]||state.pending.some(function(p){return p.index===index||p.rackIndex===rackIndex;}))return false;
        state.pending.push({index:index,rackIndex:rackIndex});return true;
    }
    function remove(state,index){if(!active(state))return false;var n=state.pending.findIndex(function(p){return p.index===index;});if(n<0)return false;state.pending.splice(n,1);return true;}
    function neighbours(index){var x=index%SIZE,y=Math.floor(index/SIZE),out=[];if(x>0)out.push(index-1);if(x<8)out.push(index+1);if(y>0)out.push(index-SIZE);if(y<8)out.push(index+SIZE);return out;}
    function path(board,index,vertical){var step=vertical?SIZE:1,start=index;while(start-step>=0&&(vertical||Math.floor((start-step)/SIZE)===Math.floor(start/SIZE))&&board[start-step])start-=step;var cells=[];for(var p=start;p<81&&(vertical||Math.floor(p/SIZE)===Math.floor(start/SIZE))&&board[p];p+=step)cells.push(p);return cells;}
    function validateMove(state){
        function fail(message){return{ok:false,message:message};}
        if(!active(state)||!state.pending.length)return fail('手札を選び、盤面にTileを置いてください。');
        var pending=state.pending, row=Math.floor(pending[0].index/SIZE),col=pending[0].index%SIZE;
        var horizontal=pending.every(function(p){return Math.floor(p.index/SIZE)===row;}),vertical=pending.every(function(p){return p.index%SIZE===col;});
        if(!horizontal&&!vertical)return fail('新しいTileは同じ行または列に置いてください。');
        var board=state.board.slice();pending.forEach(function(p){board[p.index]=state.rack[p.rackIndex];});
        var main=path(board,pending[0].index,!horizontal);
        if(pending.some(function(p){return main.indexOf(p.index)===-1;}))return fail('Tileの間に空白を残さないでください。');
        var first=state.board.every(function(value){return !value;});
        if(first&&!board[CENTER])return fail('初手は中央の印を通してください。');
        if(!first&&!pending.some(function(p){return neighbours(p.index).some(function(i){return !!state.board[i];});}))return fail('既存のTileにつなげてください。');
        var words=[],seen=new Set();pending.forEach(function(p){[false,true].forEach(function(v){var cells=path(board,p.index,v),key=cells.join(',');if(cells.length>1&&!seen.has(key)){seen.add(key);words.push({word:cells.map(function(i){return board[i];}).join(''),cells:cells});}});});
        if(!words.length)return fail('2文字以上の単語を作ってください。');
        var invalid=words.filter(function(w){return !DICTIONARY.has(w.word);});
        if(invalid.length)return fail((japanese?'日本語':'英語')+'辞書に未登録: '+invalid.map(function(w){return w.word;}).join(' / '));
        return{ok:true,board:board,words:words,points:words.reduce(function(n,w){return n+w.word.length;},0)};
    }
    function submit(state){var result=validateMove(state);if(!result.ok)return result;state.board=result.board;state.pending.forEach(function(p){state.rack[p.rackIndex]=state.pool.length?state.pool.pop():'';});state.pending=[];state.score+=result.points;state.turns++;if(state.board.every(Boolean)||(!state.pool.length&&state.rack.every(function(l){return !l;})))state.status='gameover';return result;}
    function exchange(state,random){if(!active(state)||state.pending.length||!state.pool.length)return false;state.rack.forEach(function(l){if(l)state.pool.push(l);});shuffle(state.pool,random);state.rack=Array.from({length:7},function(){return state.pool.length?state.pool.pop():'';});state.turns++;return true;}
    function parseState(raw){
        if(typeof raw!=='string'||raw.length>16384)return null;
        try{var s=JSON.parse(raw);if(!s||(japanese&&s.language!=='ja')||s.schema!==SCHEMA||s.wordlist!==WORDLIST||!['ready','playing','gameover'].includes(s.status)||!Number.isSafeInteger(s.score)||s.score<0||s.score>10000||!Number.isSafeInteger(s.turns)||s.turns<0||s.turns>1000000)return null;
            if(!Array.isArray(s.board)||s.board.length!==81||!Array.isArray(s.rack)||s.rack.length!==7||!Array.isArray(s.pool)||s.pool.length>BAG.length||!Array.isArray(s.pending)||s.pending.length>7)return null;
            if(s.board.concat(s.rack).some(function(v){return !validLetter(v,true);})||s.pool.some(function(v){return !validLetter(v,false);}))return null;
            var counts=Object.create(null);s.board.concat(s.rack,s.pool).filter(Boolean).forEach(function(l){counts[l]=(counts[l]||0)+1;});
            if(Object.keys(counts).some(function(l){return !LETTERS[l]||counts[l]!==LETTERS[l];})||Object.keys(LETTERS).some(function(l){return counts[l]!==LETTERS[l];}))return null;
            var cells=new Set(),racks=new Set();for(var i=0;i<s.pending.length;i++){var p=s.pending[i];if(!p||!Number.isInteger(p.index)||p.index<0||p.index>=81||!Number.isInteger(p.rackIndex)||p.rackIndex<0||p.rackIndex>=7||s.board[p.index]||!s.rack[p.rackIndex]||cells.has(p.index)||racks.has(p.rackIndex))return null;cells.add(p.index);racks.add(p.rackIndex);}
            var restored={schema:SCHEMA,wordlist:WORDLIST,board:s.board.slice(),rack:s.rack.slice(),pool:s.pool.slice(),pending:s.pending.map(function(p){return{index:p.index,rackIndex:p.rackIndex};}),score:s.score,turns:s.turns,status:s.status};if(japanese)restored.language='ja';return restored;
        }catch(error){return null;}
    }
    function mount(context){
        var state=createState(),userPaused=false,suspended=false,destroyed=false,selected=null,message='',storageMode='memory',key=context.stateKey,memory=null;
        if(key)['localStorage','sessionStorage'].some(function(name){try{var restored=parseState(window[name].getItem(key));if(restored){state=restored;storageMode=name;return true;}}catch(error){}return false;});
        function save(){memory=JSON.stringify(state);if(!key){storageMode='memory';return;}try{window.localStorage.setItem(key,memory);storageMode='localStorage';return;}catch(error){}try{window.sessionStorage.setItem(key,memory);storageMode='sessionStorage';return;}catch(error){}storageMode='memory';}
        var wrap=document.createElement('div');wrap.className='word-tiles-wrap';context.stage.appendChild(wrap);
        function node(tag,className,text){var el=document.createElement(tag);el.className=className;if(text!==undefined)el.textContent=text;return el;}
        var info=node('p','word-tiles-info text-muted'),board=node('div','word-tiles-board'),rack=node('div','word-tiles-rack'),controls=node('div','word-tiles-actions'),note=node('p','word-tiles-save-note text-muted');
        board.setAttribute('role','grid');board.setAttribute('aria-label',(japanese?'Word Tiles 日本語':'Word Tiles')+' 9×9盤面');board.setAttribute('aria-rowcount','9');board.setAttribute('aria-colcount','9');rack.setAttribute('role','group');rack.setAttribute('aria-label','手札');
        var cells=[],tiles=[],buttons=[];for(var r=0;r<SIZE;r++){var row=node('div','word-tiles-row');row.setAttribute('role','row');for(var c=0;c<SIZE;c++){var index=r*SIZE+c,cell=node('button','word-tiles-cell');cell.type='button';cell.setAttribute('role','gridcell');cell.setAttribute('data-word-cell',String(index));cell.setAttribute('aria-rowindex',String(r+1));cell.setAttribute('aria-colindex',String(c+1));cell.tabIndex=index===CENTER?0:-1;row.appendChild(cell);cells.push(cell);}board.appendChild(row);}
        for(var i=0;i<7;i++){var tile=node('button','btn btn-outline-secondary word-tiles-tile');tile.type='button';tile.setAttribute('data-word-rack',String(i));rack.appendChild(tile);tiles.push(tile);}
        [['submit','確定'],['clear','配置取消'],['exchange','手札交換'],['finish','Finish']].forEach(function(entry){var button=node('button','btn btn-sm btn-outline-secondary word-tiles-action',entry[1]);button.type='button';button.setAttribute('data-word-action',entry[0]);controls.appendChild(button);buttons.push(button);});
        var help=node('p','game-widget-help text-muted','手札→盤面の順に選び、確定します。初手は中央。次から既存Tileにつなげます。各文字1点、交差でできた単語も加点。未確定Tileを押すと戻せます。Bonusなし。Finishで終了。');
        var dictionary=node('details','word-tiles-dictionary'),summary=node('summary','', '固定'+(japanese?'日本語':'英語')+'辞書（'+DICTIONARY.size+'語）');
        var search=node('input','form-control word-tiles-search'),results=node('p','word-tiles-search-results',(japanese?'ひらがな2〜9文字。カタカナ・半角カナも検索できます。':'英字2〜9文字を入力。')+'完全一致の判定と先頭一致の候補を最大50語表示します。');
        search.type='search';search.maxLength=japanese?18:9;search.placeholder=japanese?'例: さくら / ガッコウ':'例: CAT / ZEBRA';search.setAttribute('aria-label',(japanese?'日本語':'英語')+'辞書を検索');results.setAttribute('role','status');
        dictionary.append(summary,search,results);if(japanese){var attribution=node('a','word-tiles-attribution','JMdict（EDRDG）由来・CC BY-SA 4.0／出典・License');attribution.href='./js/word-tiles-dictionary-ja-notice.txt';attribution.target='_blank';attribution.rel='noopener';dictionary.appendChild(attribution);help.textContent+=' 日本語は読みで判定。濁音・小文字・長音は各1Tileです。';}wrap.append(info,board,rack,controls,note,help,dictionary);
        context.on(search,'input',function(){var query=normalize(search.value);if(query.length<2||query.length>9||Array.from(query).some(function(c){return !validLetter(c,false);})){results.textContent=(japanese?'ひらがな':'英字')+'2〜9文字を入力してください。';return;}var matches=[];for(var i=0;i<WORDS.length&&matches.length<50;i++){if(WORDS[i].indexOf(query)===0)matches.push(WORDS[i]);}results.textContent=(DICTIONARY.has(query)?query+'：登録あり。':query+'：未登録。')+' 先頭一致（最大50語）: '+(matches.join(' · ')||'なし');});
        function enabled(){return !destroyed&&active(state)&&!userPaused&&!suspended;}
        function render(){
            var paused=userPaused||suspended,play=enabled();
            info.textContent='Turns '+state.turns+' · 残り '+state.pool.length+'枚';
            cells.forEach(function(cell,index){var pending=state.pending.find(function(p){return p.index===index;}),letter=pending?state.rack[pending.rackIndex]:state.board[index];cell.textContent=letter||(index===CENTER?'·':'');cell.disabled=!play||!!state.board[index];cell.classList.toggle('word-tiles-pending',!!pending);cell.classList.toggle('word-tiles-locked',!!state.board[index]);cell.setAttribute('aria-label',(Math.floor(index/SIZE)+1)+'行'+(index%SIZE+1)+'列 '+(letter||'空白')+(pending?' 未確定':state.board[index]?' 確定':''));});
            tiles.forEach(function(tile,index){tile.textContent=state.rack[index]||'–';tile.disabled=!play||!state.rack[index]||state.pending.some(function(p){return p.rackIndex===index;});tile.setAttribute('aria-pressed',selected===index?'true':'false');tile.setAttribute('aria-label','手札'+(index+1)+' '+(state.rack[index]||'空'));});
            buttons.forEach(function(button){var action=button.getAttribute('data-word-action');button.disabled=!play||(action==='exchange'&&(state.pending.length>0||!state.pool.length));});
            note.textContent=storageMode==='localStorage'?'盤面・手札・未確定配置・Scoreをこの端末に保存します。':storageMode==='sessionStorage'?'盤面はこのTabを閉じるまで保存します。':'保存できないため、この画面内だけで盤面を保持します。';
            var status=state.status==='ready'?'Startで開始します。':state.status==='gameover'?'GAME OVER。Restartで新しく遊べます。':paused?(userPaused?'Pause中です。Resumeで再開します。':'非表示またはModal表示のため停止中です。'):message||'手札を選び、単語を作ってください。';
            context.update({score:state.score,message:status,started:state.status!=='ready',playing:active(state),paused:paused,status:active(state)&&paused?'paused':state.status});
        }
        context.on(rack,'click',function(event){var button=event.target.closest('[data-word-rack]');if(!button||!enabled())return;selected=Number(button.getAttribute('data-word-rack'));message='置く空白マスを選んでください。';render();});
        context.on(board,'click',function(event){var button=event.target.closest('[data-word-cell]');if(!button||!enabled())return;var index=Number(button.getAttribute('data-word-cell'));if(remove(state,index)){message='Tileを手札へ戻しました。';}else if(selected!==null&&place(state,index,selected)){selected=null;message='確定で単語を判定します。';}else message='先に手札を選んでください。';save();render();});
        context.on(board,'keydown',function(event){if(!enabled()||event.altKey||event.ctrlKey||event.metaKey)return;var offsets={ArrowLeft:-1,ArrowRight:1,ArrowUp:-SIZE,ArrowDown:SIZE};if(!Object.prototype.hasOwnProperty.call(offsets,event.key))return;var target=event.target.closest('[data-word-cell]');if(!target)return;event.preventDefault();event.stopPropagation();var index=Number(target.getAttribute('data-word-cell')),next=index+offsets[event.key];if(next>=0&&next<81&&(Math.abs(offsets[event.key])!==1||Math.floor(next/SIZE)===Math.floor(index/SIZE))&&!cells[next].disabled){cells.forEach(function(c){c.tabIndex=-1;});cells[next].tabIndex=0;cells[next].focus({preventScroll:true});}});
        context.on(controls,'click',function(event){var button=event.target.closest('[data-word-action]');if(!button||!enabled())return;var action=button.getAttribute('data-word-action');if(action==='submit'){var result=submit(state);message=result.ok?result.words.map(function(w){return w.word;}).join(' / ')+' +'+result.points+'点':result.message;if(result.ok)selected=null;}else if(action==='clear'){state.pending=[];selected=null;message='未確定配置を取り消しました。';}else if(action==='exchange'){if(exchange(state)){selected=null;message='手札を交換しました。同じ文字が戻ることもあります。';}}else if(action==='finish'){state.pending=[];selected=null;state.status='gameover';message='';}save();render();});
        save();render();
        return{restart:function(){state=createState();state.status='playing';selected=null;userPaused=false;message='';save();render();cells[CENTER].focus({preventScroll:true});},togglePause:function(){if(!active(state))return;userPaused=!userPaused;render();},setSuspended:function(value){if(suspended===value)return;suspended=value;render();},resize:function(){},destroy:function(){destroyed=true;}};
    }
    var api={normalize:normalize,size:SIZE,center:CENTER,words:Array.from(DICTIONARY),bag:BAG.slice(),createState:createState,place:place,remove:remove,validateMove:validateMove,submit:submit,exchange:exchange,parseState:parseState};
    return{api:api,mount:mount};
    }
    window.RssWordTilesFactory=build;
    var english=null;function englishGame(){if(!english){english=build(window.RssWordTilesEnglish,false);window.RssWordTiles=english.api;}return english;}
    if(window.RssWordTilesEnglish)englishGame();
    window.RssGameWidget.register('word_tiles',function(context){return englishGame().mount(context);});
})(window,document);
