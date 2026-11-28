/* Memo-only saved snapshot downloads and timestamp display. */
(function(root){
    'use strict';
    function filename(title,id,ext){
        const clean=Array.from(String(title||'Memo').replace(/[\x00-\x1f\x7f<>:"/\\|?*\u202a-\u202e\u2066-\u2069]/g,'_').trim()).slice(0,60).join('').replace(/[. ]+$/,'')||'Memo';
        return 'memo-'+String(id).replace(/[^0-9]/g,'')+'-'+clean+'.'+(ext==='md'?'md':'txt');
    }
    function plainText(doc,source,format,renderer){
        if(format!=='markdown'||!renderer)return source;
        const host=doc.createElement('div');
        renderer.render(host,source,{interactive:false});
        // Traverse the same safe DOM used for display. Keep list numbers/tasks,
        // table columns, line breaks and code; remove only supported decoration.
        function inline(el){
            if(el.nodeType===3)return el.nodeValue;
            if(el.nodeType!==1)return '';
            if(el.tagName==='BR')return '\n';
            if(el.tagName==='INPUT')return el.checked?'[x] ':'[ ] ';
            const text=Array.from(el.childNodes).map(inline).join('');
            if(el.tagName==='A'){
                const href=el.getAttribute('href')||'';
                return href && text!==href && text.replace(/\/$/,'')!==href.replace(/\/$/,'') ? text+' ('+href+')' : text;
            }
            return text;
        }
        function block(el){
            if(el.tagName==='UL'||el.tagName==='OL'){
                const ordered=el.tagName==='OL',start=ordered?el.start:1;
                return Array.from(el.children).map((li,i)=>(ordered?(start+i)+'. ':li.classList.contains('memo-md-task-item')?'':'- ')+inline(li)).join('\n');
            }
            if(el.classList.contains('memo-md-table-scroll'))return Array.from(el.querySelectorAll('tr')).map(r=>Array.from(r.children).map(inline).join('\t')).join('\n');
            if(el.tagName==='PRE')return el.textContent;
            return inline(el);
        }
        return Array.from(host.childNodes).map(n=>n.nodeType===1?block(n):inline(n)).join('\n\n');
    }
    function syncTimestamp(card){
        const t=card&&card.querySelector('.memo-updated-at');if(!t)return;
        const raw=card.getAttribute('data-memo-updated-at')||'';
        if(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(raw)){
            t.dateTime=raw.replace(' ','T');t.textContent=raw.slice(0,16);t.title=raw;
        }else{t.removeAttribute('datetime');t.textContent='—';t.removeAttribute('title');}
    }
    function download(card,ext){
        if(!card||!['txt','md'].includes(ext))return false;
        const raw=card.getAttribute('data-memo-raw'),format=card.getAttribute('data-memo-format'),id=card.getAttribute('data-memo-id');
        if(raw===null||!['text','markdown'].includes(format)||!/^[1-9]\d*$/.test(id||''))return false;
        const title=card.querySelector('.memo-title')?.textContent||'Memo',doc=card.ownerDocument;
        const text=ext==='md'?raw:plainText(doc,raw,format,root.RssMemoMarkdown);
        const blob=new root.Blob([text],{type:ext==='md'?'text/markdown;charset=utf-8':'text/plain;charset=utf-8'});
        const url=root.URL.createObjectURL(blob),a=doc.createElement('a');
        a.href=url;a.download=filename(title,id,ext);
        doc.body.append(a);
        try{a.click();}finally{a.remove();root.setTimeout(()=>root.URL.revokeObjectURL(url),60000);}
        return true;
    }
    const api={filename,plainText,syncTimestamp,download};
    if(typeof module!=='undefined'&&module.exports)module.exports=api;
    if(root)root.RssMemoManagement=api;
}(typeof window!=='undefined'?window:null));
