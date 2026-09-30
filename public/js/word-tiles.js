/* Word Tiles C1: original 9x9 solo board; hand-maintained starter list.
 * No third-party dictionary dataset is imported. C2 will expand this list. */
(function(window,document){
    'use strict';
    var SIZE=9, CENTER=40, SCHEMA=1, WORDLIST=1;
    var WORDS=('AN AS AT BE BY DO GO HE IF IN IS IT ME MY NO OF ON OR SO TO UP US WE '+
        'ACE ACT ADD AGE AGO AID AIM AIR ALL AND ANT ANY APE ARC ARE ARM ART ASH ASK ATE '+
        'BAD BAG BAN BAR BAT BAY BED BEE BEG BET BID BIG BIN BIT BOW BOX BOY BUD BUG BUS BUT BUY '+
        'CAB CAN CAP CAR CAT COW CRY CUP CUT DAY DEN DID DIE DIG DOG DOT DRY DUE DUG EAR EAT EGG END ERA EYE '+
        'FAN FAR FAT FED FEE FEW FIG FIN FIT FIX FLY FOG FOR FOX FUN FUR GAP GAS GET GOD GOT GUM GUN GUY '+
        'HAD HAM HAS HAT HAY HEN HER HID HIM HIP HIS HIT HOG HOT HOW HUG HUT ICE ILL INK ITS JAM JAR JAW JET JOB JOY '+
        'KEY KID KIT LAB LAP LAW LAY LED LEG LET LID LIE LIP LOG LOT LOW MAD MAN MAP MAT MAY MET MIX MOB MUD MUG '+
        'NAP NET NEW NOD NOR NOT NOW NUT OAK OAR ODD OFF OIL OLD ONE OUR OUT OWL OWN PAN PAT PAW PAY PEA PEN PET PIE PIG PIN PIT POT PUT '+
        'RAG RAM RAN RAP RAT RAW RAY RED RID RIP ROB ROD ROT ROW RUB RUG RUN SAD SAT SAW SAY SEA SEE SET SEW SHE SHY SIP SIT SIX SKI SKY SON SOW SPA SPY SUE SUM SUN '+
        'TAB TAG TAN TAP TAR TAX TEA TEN THE TIE TIP TOE TON TOO TOP TOW TOY TRY TUB TWO USE VAN VET VIA VOW WAR WAS WAX WAY WEB WET WHO WHY WIN WIT WON YES YET YOU ZOO '+
        'ABLE ALSO AREA ARMY AWAY BABY BACK BALL BAND BANK BARK BASE BATH BEAR BEAT BEEN BELL BELT BEST BIRD BLUE BOAT BODY BONE BOOK BORN BOTH BOWL '+
        'CAKE CALL CALM CAMP CARD CARE CART CASE CAVE CHAT CITY CLAY CLUB COAL COAT CODE COLD COME COOK COOL COPY COST CRAB CREW CROP CROW '+
        'DARK DATA DATE DAWN DAYS DEAR DEEP DEER DESK DICE DIET DIRT DISH DOES DOOR DOWN DRAW DROP DRUM DUCK DUST DUTY EACH EARN EAST EASY EDGE ELSE EVEN EVER EXIT FACE FACT FAIR FALL FARM FAST FEAR FEED FEEL FEET FELL FELT FILE FILL FIND FINE FIRE FISH FIVE FLAT FLOW FOOD FOOT FORM FOUR FREE FROM FROG FULL GAME GATE GAVE GIFT GIRL GIVE GLAD GOAL GOAT GOLD GOLF GONE GOOD GRAB GRAY GREW GROW '+
        'HAIR HALF HALL HAND HARD HARE HARM HATE HAVE HEAD HEAR HEAT HELD HELP HERE HERO HIDE HIGH HILL HOLD HOLE HOME HOPE HORN HOUR HUGE IDEA INTO IRON ITEM JOIN JUMP JUST KEEP KEPT KIND KING KITE KNEE KNEW KNOW '+
        'LACK LAKE LAMB LAMP LAND LANE LAST LATE LEAD LEAF LEFT LEND LENS LESS LIFE LIFT LIKE LINE LINK LION LIST LIVE LOAD LOAN LOCK LONG LOOK LORD LOSE LOSS LOST LOVE LUCK MADE MAIL MAIN MAKE MANY MARK MASK MATH MEAL MEAN MEAT MEET MILE MILK MILL MIND MINE MISS MODE MOON MORE MOST MOVE MUCH MUST NAME NEAR NEAT NECK NEED NEST NEWS NEXT NICE NINE NODE NONE NOSE NOTE '+
        'ONCE ONLY OPEN OVER PAGE PAID PAIN PAIR PARK PART PASS PAST PATH PEAR PICK PINK PLAN PLAY PLUS POEM POET POOL PORT POST PULL PURE PUSH RACE RAIN RANK RATE READ REAL RICE RICH RIDE RING RISE ROAD ROCK ROLE ROOF ROOM ROOT ROPE ROSE RULE SAFE SAID SAIL SALE SALT SAME SAND SAVE SEAT SEED SEEK SEEM SEEN SELF SELL SEND SENT SHIP SHOE SHOP SHOW SIDE SIGN SING SITE SIZE SKIN SLOW SNOW SOAP SOFT SOIL SOLD SOME SONG SOON SORT SOUL STAR STAY STEM STEP STOP SUCH SUIT '+
        'TAIL TAKE TALE TALK TALL TANK TAPE TASK TEAM TELL TEND TENT TERM TEST TEXT THAN THAT THEM THEN THEY THIN THIS TIDE TIED TILE TIME TINY TOLD TONE TOOK TOOL TOUR TOWN TREE TRIP TRUE TUBE TURN TWIN TYPE UNIT UPON USED USER VAST VERY VIEW VOTE WAIT WAKE WALK WALL WANT WARM WASH WAVE WAYS WEAK WEAR WEEK WELL WENT WERE WEST WHAT WHEN WHOM WIDE WIFE WILD WILL WIND WINE WING WIRE WISE WISH WITH WOLF WOOD WORD WORE WORK WORM WORN YEAR YOUR ZERO '+
        'APPLE BEACH BERRY BREAD BRICK BRING CHAIR CHASE CHEST CLEAN CLEAR CLOUD COAST COLOR DANCE DREAM DRINK EARTH EIGHT ENJOY EVERY FIELD FIRST FLOOR FLOWER FOCUS FRESH FRUIT GLASS GRASS GREEN GROUP HAPPY HEART HORSE HOUSE LARGE LEARN LEAST LIGHT LUNCH MOUSE MUSIC NIGHT NORTH OCEAN OFTEN ORDER PAPER PARTY PEACE PIANO PLACE PLANT PLATE POINT POWER PRICE QUICK QUIET RADIO REACH READY RIGHT RIVER ROUND SCORE SEVEN SHAPE SHARE SHEEP SHEET SHELF SHINE SHIRT SHORT SLEEP SMALL SMILE SOLAR SOUND SOUTH SPACE SPEAK SPOON SPORT STAND START STATE STEAM STONE STORE STORY SUGAR SWEET TABLE TEACH THANK THEIR THERE THESE THING THINK THREE TILES TODAY TOUCH TRACK TRAIN TRUST UNDER UNTIL VALUE VIDEO VISIT VOICE WATER WHEEL WHERE WHITE WHOLE WOMAN WORLD WRITE YOUNG').split(/\s+/);
    var DICTIONARY=new Set(WORDS), LETTERS={A:6,B:1,C:3,D:3,E:10,F:1,G:2,H:2,I:6,J:1,K:1,L:3,M:2,N:5,O:6,P:2,Q:1,R:5,S:5,T:6,U:3,V:1,W:1,X:1,Y:1,Z:1};
    var BAG=Object.keys(LETTERS).reduce(function(out,key){return out.concat(new Array(LETTERS[key]).fill(key));},[]);
    function shuffle(array,random){for(var i=array.length-1;i>0;i--){var value=Number((random||Math.random)()),j=Math.floor((Number.isFinite(value)?Math.max(0,Math.min(.999999,value)):0)*(i+1));var old=array[i];array[i]=array[j];array[j]=old;}return array;}
    function createState(random){var rack=['C','A','T','R','E','S','O'],pool=BAG.slice();rack.forEach(function(letter){pool.splice(pool.indexOf(letter),1);});return{schema:SCHEMA,wordlist:WORDLIST,board:new Array(SIZE*SIZE).fill(''),rack:rack,pool:shuffle(pool,random),pending:[],score:0,turns:0,status:'ready'};}
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
        if(invalid.length)return fail('C1辞書に未登録: '+invalid.map(function(w){return w.word;}).join(' / '));
        return{ok:true,board:board,words:words,points:words.reduce(function(n,w){return n+w.word.length;},0)};
    }
    function submit(state){var result=validateMove(state);if(!result.ok)return result;state.board=result.board;state.pending.forEach(function(p){state.rack[p.rackIndex]=state.pool.length?state.pool.pop():'';});state.pending=[];state.score+=result.points;state.turns++;if(state.board.every(Boolean)||(!state.pool.length&&state.rack.every(function(l){return !l;})))state.status='gameover';return result;}
    function exchange(state,random){if(!active(state)||state.pending.length||!state.pool.length)return false;state.rack.forEach(function(l){if(l)state.pool.push(l);});shuffle(state.pool,random);state.rack=Array.from({length:7},function(){return state.pool.length?state.pool.pop():'';});state.turns++;return true;}
    function parseState(raw){
        if(typeof raw!=='string'||raw.length>16384)return null;
        try{var s=JSON.parse(raw);if(!s||s.schema!==SCHEMA||s.wordlist!==WORDLIST||!['ready','playing','gameover'].includes(s.status)||!Number.isSafeInteger(s.score)||s.score<0||s.score>10000||!Number.isSafeInteger(s.turns)||s.turns<0||s.turns>1000000)return null;
            if(!Array.isArray(s.board)||s.board.length!==81||!Array.isArray(s.rack)||s.rack.length!==7||!Array.isArray(s.pool)||s.pool.length>BAG.length||!Array.isArray(s.pending)||s.pending.length>7)return null;
            if(s.board.concat(s.rack).some(function(v){return typeof v!=='string'||!/^([A-Z])?$/.test(v);})||s.pool.some(function(v){return typeof v!=='string'||! /^[A-Z]$/.test(v);}))return null;
            var counts=Object.create(null);s.board.concat(s.rack,s.pool).filter(Boolean).forEach(function(l){counts[l]=(counts[l]||0)+1;});
            if(Object.keys(counts).some(function(l){return !LETTERS[l]||counts[l]!==LETTERS[l];})||Object.keys(LETTERS).some(function(l){return counts[l]!==LETTERS[l];}))return null;
            var cells=new Set(),racks=new Set();for(var i=0;i<s.pending.length;i++){var p=s.pending[i];if(!p||!Number.isInteger(p.index)||p.index<0||p.index>=81||!Number.isInteger(p.rackIndex)||p.rackIndex<0||p.rackIndex>=7||s.board[p.index]||!s.rack[p.rackIndex]||cells.has(p.index)||racks.has(p.rackIndex))return null;cells.add(p.index);racks.add(p.rackIndex);}
            return{schema:SCHEMA,wordlist:WORDLIST,board:s.board.slice(),rack:s.rack.slice(),pool:s.pool.slice(),pending:s.pending.map(function(p){return{index:p.index,rackIndex:p.rackIndex};}),score:s.score,turns:s.turns,status:s.status};
        }catch(error){return null;}
    }
    function mount(context){
        var state=createState(),userPaused=false,suspended=false,destroyed=false,selected=null,message='',storageMode='memory',key=context.stateKey,memory=null;
        if(key)['localStorage','sessionStorage'].some(function(name){try{var restored=parseState(window[name].getItem(key));if(restored){state=restored;storageMode=name;return true;}}catch(error){}return false;});
        function save(){memory=JSON.stringify(state);if(!key){storageMode='memory';return;}try{window.localStorage.setItem(key,memory);storageMode='localStorage';return;}catch(error){}try{window.sessionStorage.setItem(key,memory);storageMode='sessionStorage';return;}catch(error){}storageMode='memory';}
        var wrap=document.createElement('div');wrap.className='word-tiles-wrap';context.stage.appendChild(wrap);
        function node(tag,className,text){var el=document.createElement(tag);el.className=className;if(text!==undefined)el.textContent=text;return el;}
        var info=node('p','word-tiles-info text-muted'),board=node('div','word-tiles-board'),rack=node('div','word-tiles-rack'),controls=node('div','word-tiles-actions'),note=node('p','word-tiles-save-note text-muted');
        board.setAttribute('role','grid');board.setAttribute('aria-label','Word Tiles 9×9盤面');board.setAttribute('aria-rowcount','9');board.setAttribute('aria-colcount','9');rack.setAttribute('role','group');rack.setAttribute('aria-label','手札');
        var cells=[],tiles=[],buttons=[];for(var r=0;r<SIZE;r++){var row=node('div','word-tiles-row');row.setAttribute('role','row');for(var c=0;c<SIZE;c++){var index=r*SIZE+c,cell=node('button','word-tiles-cell');cell.type='button';cell.setAttribute('role','gridcell');cell.setAttribute('data-word-cell',String(index));cell.setAttribute('aria-rowindex',String(r+1));cell.setAttribute('aria-colindex',String(c+1));cell.tabIndex=index===CENTER?0:-1;row.appendChild(cell);cells.push(cell);}board.appendChild(row);}
        for(var i=0;i<7;i++){var tile=node('button','btn btn-outline-secondary word-tiles-tile');tile.type='button';tile.setAttribute('data-word-rack',String(i));rack.appendChild(tile);tiles.push(tile);}
        [['submit','確定'],['clear','配置取消'],['exchange','手札交換'],['finish','Finish']].forEach(function(entry){var button=node('button','btn btn-sm btn-outline-secondary word-tiles-action',entry[1]);button.type='button';button.setAttribute('data-word-action',entry[0]);controls.appendChild(button);buttons.push(button);});
        var help=node('p','game-widget-help text-muted','手札→盤面の順に選び、確定します。初手は中央。次から既存Tileにつなげます。各文字1点、交差でできた単語も加点。未確定Tileを押すと戻せます。Bonusなし。Finishで終了。');
        var dictionary=node('details','word-tiles-dictionary'),summary=node('summary','', 'C1固定英語辞書（'+DICTIONARY.size+'語）');dictionary.append(summary,node('p','',Array.from(DICTIONARY).sort().join(' · ')));wrap.append(info,board,rack,controls,note,help,dictionary);
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
    window.RssWordTiles={size:SIZE,center:CENTER,words:Array.from(DICTIONARY),bag:BAG.slice(),createState:createState,place:place,remove:remove,validateMove:validateMove,submit:submit,exchange:exchange,parseState:parseState};
    window.RssGameWidget.register('word_tiles',mount);
})(window,document);
