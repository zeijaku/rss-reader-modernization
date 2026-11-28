/* Memo editor session: shared by inline, create, and settings. */
(function(root){
    'use strict';
    const LIMIT=16000, HISTORY_LIMIT=100, tick=String.fromCharCode(96);
    const selector='.memo-inline-textarea, .registerMemoBody, .changeMemoBody';
    const states=new WeakMap();
    function count(s){return Array.from(String(s).replace(/\r\n?/g,'\n')).length;}
    function snapshot(t){return {value:t.value,start:t.selectionStart,end:t.selectionEnd,direction:t.selectionDirection||'none'};}
    class History {
        constructor(initial){this.reset(initial);}
        reset(initial){this.current=initial;this.undoStack=[];this.redoStack=[];}
        record(before,after){
            if(before.value===after.value){this.current=after;return false;}
            this.undoStack.push(before);
            if(this.undoStack.length>HISTORY_LIMIT)this.undoStack.shift();
            this.redoStack=[];this.current=after;return true;
        }
        move(redo,now){
            const from=redo?this.redoStack:this.undoStack,to=redo?this.undoStack:this.redoStack;
            if(!from.length)return null;
            to.push(now);if(to.length>HISTORY_LIMIT)to.shift();
            this.current=from.pop();return this.current;
        }
    }
    const actions=[
        ['bold','太字','B'],['italic','斜体','I'],['strike','取り消し線','S'],
        ['heading','見出し','H'],['bullet','箇条書き','•'],['number','番号付きリスト','1.'],
        ['task','チェックリスト','☑'],['link','リンク','↗'],['inlinecode','インラインコード','<>'],
        ['code','コードブロック','{ }'],['table','テーブル','▦']
    ];
    // Return a complete next source and selection; never interpret input as HTML.
    function transform(value,start,end,action){
        if(!Number.isInteger(start)||!Number.isInteger(end)||start<0||end<start||end>value.length)return null;
        const selected=value.slice(start,end);
        function replace(a,b,text,x,y){return {value:value.slice(0,a)+text+value.slice(b),start:a+x,end:a+y,direction:'none'};}
        function wrap(left,right,fallback){
            const text=selected||fallback;
            return replace(start,end,left+text+right,left.length,left.length+text.length);
        }
        if(action==='bold')return wrap('**','**','文字');
        if(action==='italic')return wrap('*','*','文字');
        if(action==='strike')return wrap('~~','~~','文字');
        if(action==='inlinecode'){
            const runs=selected.match(/\x60+/g)||[],n=Math.max(1,...runs.map(x=>x.length+1));
            return wrap(tick.repeat(n),tick.repeat(n),'code');
        }
        if(action==='link'){
            const label=selected||'リンク',href='https://example.com/';
            // URL selection is ready for direct replacement.
            const text='['+label.replace(/[\\[\]]/g,'\\$&')+']('+href+')';
            const x=text.indexOf(href);return replace(start,end,text,x,x+href.length);
        }
        if(['heading','bullet','number','task'].includes(action)){
            const a=value.lastIndexOf('\n',start-1)+1;
            // When the selection ends just after a newline, do not prefix the next line.
            const last=end>start && value[end-1]==='\n'?end-1:end;
            const next=value.indexOf('\n',last),b=next<0?value.length:next;
            const block=value.slice(a,b);
            const lines=block.split('\n');
            const prefix=(i)=>action==='heading'?'## ':action==='bullet'?'- ':action==='number'?(i+1)+'. ':'- [ ] ';
            const out=lines.map((line,i)=>prefix(i)+(line||'項目')).join('\n');
            return replace(a,b,out,0,out.length);
        }
        if(action==='code'||action==='table'){
            const before=start>0&&value[start-1]!=='\n'?'\n':'';
            const after=end<value.length&&value[end]!=='\n'?'\n':'';
            let text, x, y;
            if(action==='code'){
                const n=Math.max(3,...(selected.match(/\x60+/g)||[]).map(v=>v.length+1)),f=tick.repeat(n),code=selected||'コード';
                text=f+'\n'+code+'\n'+f;x=f.length+1;y=x+code.length;
            }else{
                text='| 項目 | 内容 |\n| --- | --- |\n| '+(selected?selected.replace(/\n/g,' ').replace(/\|/g,'\\|'):'A')+' | B |';x=2;y=4;
            }
            return replace(start,end,before+text+after,before.length+x,before.length+y);
        }
        return null;
    }
    function makeButton(doc,action,label,text){
        const b=doc.createElement('button');b.type='button';b.className='btn btn-sm btn-outline-secondary memo-editor-button';
        b.dataset.memoAction=action;b.title=label;b.setAttribute('aria-label',label);b.textContent=text;return b;
    }
    function mode(s){
        const form=s.t.closest('form');
        if(form){const f=form.querySelector('.registerMemoFormat, .changeMemoFormat');return f&&f.value==='markdown'?'markdown':'text';}
        const card=s.t.closest('.memo-card');return card&&card.getAttribute('data-memo-format')==='markdown'?'markdown':'text';
    }
    function blocked(s){return s.busy||s.composing||s.t.disabled||s.t.readOnly;}
    function notify(s,text){s.notice.textContent=text||'';}
    function sync(s){
        const md=mode(s)==='markdown';
        if(!md&&s.preview)setPreview(s,false);
        s.formatting.hidden=!md;s.buttons.preview.hidden=!md;s.buttons.edit.hidden=!md;
        const lock=blocked(s);
        Object.values(s.buttons).forEach(b=>{b.disabled=lock;});
        s.buttons.undo.disabled=lock||s.preview||!s.history.undoStack.length;
        s.buttons.redo.disabled=lock||s.preview||!s.history.redoStack.length;
        s.buttons.preview.disabled=lock||s.preview;
        s.buttons.edit.disabled=lock||!s.preview;
        s.buttons.full.disabled=lock||(!s.modal && typeof s.t.ownerDocument.createElement('dialog').showModal!=='function');
        s.formatting.querySelectorAll('button').forEach(b=>{b.disabled=lock||s.preview;});
        s.buttons.full.textContent=s.full?'縮小':'全画面';s.buttons.full.setAttribute('aria-pressed',String(s.full));
    }
    function setPreview(s,next){
        s.preview=!!next&&mode(s)==='markdown';
        s.t.hidden=s.preview;s.view.hidden=!s.preview;
        if(s.preview){
            if(root.RssMemoMarkdown) root.RssMemoMarkdown.render(s.view,s.t.value,{interactive:false});
            else s.view.textContent=s.t.value;
            // Preview is read-only: no link navigation, no checklist persistence.
            s.view.querySelectorAll('a').forEach(a=>{a.removeAttribute('href');a.removeAttribute('target');a.setAttribute('aria-disabled','true');});
        }else s.view.replaceChildren();
        s.buttons.edit.setAttribute('aria-pressed',String(!s.preview));
        s.buttons.preview.setAttribute('aria-pressed',String(s.preview));
    }
    function focusText(s){if(!s.preview&&!s.t.disabled&&!s.t.readOnly)s.t.focus({preventScroll:true});}
    function apply(s,next){
        s.t.value=next.value;s.t.setSelectionRange(next.start,next.end,next.direction);
        s.history.current=snapshot(s.t);s.synthetic=true;
        try{s.t.dispatchEvent(new Event('input',{bubbles:true}));}finally{s.synthetic=false;}
        sync(s);focusText(s);
    }
    function undo(s,redo){
        if(blocked(s)||s.preview)return;
        const next=s.history.move(redo,snapshot(s.t));
        if(next){apply(s,next);notify(s,'');}
    }
    function closeFull(s,force=false){
        if(!s.full||(!force&&blocked(s)))return;
        const snap=snapshot(s.t),scroll=s.t.scrollTop;
        s.full=false;
        if(s.modal){s.modal.classList.remove('modal-fullscreen','memo-editor-modal-full');}
        else if(s.dialog){
            if(s.dialog.open)s.dialog.close();
            if(s.anchor&&s.anchor.parentNode)s.anchor.replaceWith(s.host);
            s.dialog.remove();s.dialog=null;s.anchor=null;
        }
        s.t.setSelectionRange(snap.start,snap.end,snap.direction);s.t.scrollTop=scroll;
        sync(s);if(!force)focusText(s);
    }
    function openFull(s){
        if(blocked(s)||s.full)return;
        const snap=snapshot(s.t),scroll=s.t.scrollTop;
        if(s.modal){s.full=true;s.modal.classList.add('modal-fullscreen','memo-editor-modal-full');}
        else {
            const doc=s.t.ownerDocument,dialog=doc.createElement('dialog'),anchor=doc.createComment('memo-editor-home');
            if(typeof dialog.showModal!=='function')return;
            const card=s.t.closest('.memo-card');
            if(!card)return;
            dialog.className='memo-editor-fullscreen';dialog.setAttribute('aria-label','Memo 全画面編集');
            s.host.before(anchor);card.append(dialog);dialog.append(s.host);
            s.dialog=dialog;s.anchor=anchor;s.full=true;
            dialog.addEventListener('cancel',event=>{event.preventDefault();closeFull(s);});
            try{dialog.showModal();}catch(_){closeFull(s,true);notify(s,'全画面表示にできませんでした。');return;}
        }
        s.t.setSelectionRange(snap.start,snap.end,snap.direction);s.t.scrollTop=scroll;
        sync(s);if(s.preview)s.buttons.edit.focus();else focusText(s);
    }
    function handleAction(s,action){
        if(blocked(s))return;
        if(action==='undo'||action==='redo'){undo(s,action==='redo');return;}
        if(action==='preview'||action==='edit'){setPreview(s,action==='preview');sync(s);if(!s.preview)focusText(s);return;}
        if(action==='full'){if(s.full)closeFull(s);else openFull(s);return;}
        if(s.preview||mode(s)!=='markdown')return;
        const before=snapshot(s.t),next=transform(before.value,before.start,before.end,action);
        if(!next)return;
        if(count(next.value)>LIMIT){notify(s,'16,000文字を超えるため挿入できません。');return;}
        s.history.record(before,next);apply(s,next);notify(s,'');
    }
    function bind(t){
        if(!t)return null;
        if(states.has(t))return states.get(t);
        const doc=t.ownerDocument,host=t.closest('.memo-inline-editor')||t.parentElement,modal=t.closest('.modal-dialog');
        const toolbar=doc.createElement('div');toolbar.className='memo-editor-tools';
        const controls=doc.createElement('div');controls.className='memo-editor-controls';
        const formatting=doc.createElement('div');formatting.className='memo-editor-formatting';formatting.setAttribute('role','group');formatting.setAttribute('aria-label','Markdown入力支援');
        const buttons={};for(const [action,label,text] of [['undo','元に戻す','↶'],['redo','やり直す','↷'],['edit','編集','編集'],['preview','プレビュー','表示'],['full','全画面編集','全画面']]){
            buttons[action]=makeButton(doc,action,label,text);controls.append(buttons[action]);
        }
        for(const [action,label,text] of actions)formatting.append(makeButton(doc,action,label,text));
        const notice=doc.createElement('small');notice.className='memo-editor-notice';notice.setAttribute('role','status');
        toolbar.append(controls,formatting,notice);t.before(toolbar);
        const view=doc.createElement('div');view.className='memo-markdown memo-editor-preview';view.hidden=true;view.setAttribute('aria-label','Markdownプレビュー（閲覧のみ）');view.tabIndex=0;
        t.after(view);
        const s={t,host,modal,toolbar,formatting,buttons,notice,view,history:new History(snapshot(t)),preview:false,full:false,busy:false,composing:false,compositionStart:null,before:null,synthetic:false,dialog:null,anchor:null};
        states.set(t,s);
        // Keep the textarea selection on pointer toolbar clicks, but allow keyboard activation.
        toolbar.addEventListener('mousedown',event=>{if(event.target.closest('button'))event.preventDefault();});
        toolbar.addEventListener('click',event=>{const b=event.target.closest('[data-memo-action]');if(b&&!b.disabled)handleAction(s,b.dataset.memoAction);});
        t.addEventListener('beforeinput',event=>{
            if(s.synthetic||s.composing||event.isComposing)return;
            if(event.inputType==='historyUndo'||event.inputType==='historyRedo'){
                if(event.cancelable){event.preventDefault();undo(s,event.inputType==='historyRedo');}return;
            }
            s.before=snapshot(t);
        });
        t.addEventListener('input',event=>{
            if(s.synthetic)return;
            if(s.composing||event.isComposing)return;
            const now=snapshot(t);
            s.history.record(s.before||s.history.current,now);s.before=null;notify(s,'');sync(s);
        });
        t.addEventListener('compositionstart',()=>{s.composing=true;s.compositionStart=snapshot(t);s.before=null;sync(s);});
        t.addEventListener('compositionend',()=>{
            if(!s.composing)return;
            s.composing=false;s.history.record(s.compositionStart||s.history.current,snapshot(t));s.compositionStart=null;s.before=null;sync(s);
        });
        t.addEventListener('keydown',event=>{
            if(event.isComposing||s.composing||event.keyCode===229)return;
            if((event.ctrlKey||event.metaKey)&&!event.altKey){
                const key=event.key.toLowerCase();
                if(key==='z'||key==='y'){event.preventDefault();undo(s,key==='y'||event.shiftKey);}
            }
        });
        t.addEventListener('invalid',()=>{if(s.preview){setPreview(s,false);sync(s);focusText(s);}});
        const form=t.closest('form');
        if(form){const format=form.querySelector('.registerMemoFormat,.changeMemoFormat');if(format)format.addEventListener('change',()=>{setPreview(s,false);sync(s);});}
        sync(s);return s;
    }
    function reset(t){const s=bind(t);if(!s)return;closeFull(s,true);s.composing=false;s.compositionStart=null;s.before=null;s.history.reset(snapshot(t));setPreview(s,false);notify(s,'');sync(s);}
    function end(t){const s=states.get(t);if(!s)return;closeFull(s,true);s.composing=false;s.compositionStart=null;s.before=null;s.history.reset(snapshot(t));setPreview(s,false);notify(s,'');sync(s);}
    function busy(t,next){const s=states.get(t);if(s){s.busy=!!next;sync(s);}}
    function canSubmit(t){const s=states.get(t);if(!s)return true;if(s.composing||s.busy){notify(s,s.composing?'日本語入力を確定してから保存してください。':'保存中です。');return false;}return true;}
    function lockForm(form){
        const t=form.querySelector(selector),s=bind(t);
        if(!s)return ()=>{};
        const list=Array.from(form.querySelectorAll('input:not([type=hidden]),select,textarea,button')).filter(x=>!s.toolbar.contains(x)&&x.type!=='submit');
        const previous=list.map(x=>x.disabled);
        list.forEach(x=>{x.disabled=true;});busy(t,true);
        return ()=>{list.forEach((x,i)=>{x.disabled=previous[i];});busy(t,false);};
    }
    function init(doc){
        for(const id of ['registerMemo','changeMemo']){
            const modal=doc.getElementById(id);if(!modal)continue;
            const t=modal.querySelector(selector);if(!t)continue;
            bind(t);
            modal.addEventListener('shown.bs.modal',()=>{const s=states.get(t);if(s){s.history.reset(snapshot(t));setPreview(s,false);sync(s);}});
            modal.addEventListener('hide.bs.modal',event=>{const s=states.get(t);if(s&&(s.busy||s.composing))event.preventDefault();});
            modal.addEventListener('hidden.bs.modal',()=>end(t));
        }
    }
    const api={reset,end,busy,canSubmit,lockForm,transform,History,count};
    if(typeof module!=='undefined'&&module.exports)module.exports=api;
    if(root){root.RssMemoEditor=api;if(root.document){if(root.document.readyState==='loading')root.document.addEventListener('DOMContentLoaded',()=>init(root.document),{once:true});else init(root.document);}}
}(typeof window!=='undefined'?window:null));
