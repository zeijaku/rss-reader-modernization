/* Memo-only, bounded Markdown subset. No HTML parser or network resources. */
(function (root) {
    'use strict';
    const MAX_SOURCE = 32000, MAX_INLINE_SPAN = 2048, MAX_DEPTH = 5;
    const tick = String.fromCharCode(96);
    const escapable = /[\\*_~\x60[\]{}()!#|.+<>-]/;
    function node(doc, tag, text) {
        const el = doc.createElement(tag);
        if (text !== undefined) el.textContent = text;
        return el;
    }
    function safeUrl(value) {
        if (typeof value !== 'string' || value.length > MAX_INLINE_SPAN || !/^https?:\/\//i.test(value)
            || /[\s\x00-\x1f\x7f\\]/.test(value)) return null;
        try {
            const url = new URL(value);
            return (url.protocol === 'http:' || url.protocol === 'https:') && url.hostname && !url.username && !url.password ? url.href : null;
        } catch (_) { return null; }
    }
    function link(doc, label, href) {
        const url = safeUrl(href);
        if (!url) return null;
        const a = node(doc, 'a', label);
        a.href = url; a.target = '_blank'; a.rel = 'noopener noreferrer';
        return a;
    }
    // All source text is appended as text nodes. Limit recursive inline parsing
    // and delimiter lookahead; unsupported syntax stays readable as literal text.
    function inline(parent, text, depth = 0) {
        const doc = parent.ownerDocument;
        if (depth >= MAX_DEPTH) { parent.append(doc.createTextNode(text)); return; }
        const specials = /\\|\x60+|!\[|\[|<|https?:\/\/|\*\*|__|~~|\*|_/gi;
        let last = 0, match;
        function plain(value) { if (value) parent.append(doc.createTextNode(value)); }
        while ((match = specials.exec(text))) {
            const i = match.index, token = match[0], tail = text.slice(i, i + MAX_INLINE_SPAN + token.length + 1);
            plain(text.slice(last, i));
            let consumed = token.length, out = null;
            if (token === '\\' && escapable.test(text[i+1] || '')) {
                plain(text[i+1]); consumed = 2; out = true;
            } else if (token[0] === tick) {
                // Code delimiter must be a run of the same length, not a prefix.
                const runs = /\x60+/g; runs.lastIndex = token.length;
                let close;
                while ((close = runs.exec(tail))) {
                    if (close[0].length === token.length) {
                        out = node(doc, 'code', tail.slice(token.length, close.index));
                        consumed = close.index + token.length; break;
                    }
                }
            } else if (token === '[' || token === '![') {
                const m = /^(?:!)?\[((?:\\[\\[\]]|[^\\\]\n]|\\(?![\\[\]])){0,512})\]\((<[^<>\n]{1,2048}>|(?:[^()\s]|\([^()\s]*\)){1,2048})\)/.exec(tail);
                if (m) {
                    const href = m[2][0] === '<' ? m[2].slice(1,-1) : m[2];
                    out = token === '[' ? link(doc, m[1].replace(/\\([\\[\]])/g,'$1'), href) : null;
                    if (!out) { plain(m[0]); out = true; }
                    consumed = m[0].length;
                }
            } else if (token === '<') {
                const m = /^<(https?:\/\/[^<>\s]+)>/i.exec(tail);
                if (m) {
                    out = link(doc, m[1], m[1]);
                    if (out) consumed = m[0].length;
                } else {
                    // Raw HTML is literal, including attributes; never parse it.
                    const end = tail.indexOf('>');
                    if (end >= 0) { plain(tail.slice(0,end+1)); consumed=end+1; out=true; }
                }
            } else if (/^https?:\/\//i.test(token)) {
                const m = /^https?:\/\/[^\s<>"'\x60*|、。！？「」]+/i.exec(tail);
                if (m) {
                    let href = m[0].replace(/[.,;:!?]+$/, '');
                    while (href.endsWith(')') && (href.match(/\)/g)||[]).length > (href.match(/\(/g)||[]).length) href = href.slice(0,-1);
                    out = link(doc,href,href);
                    if (out) consumed = href.length;
                }
            } else {
                const close = tail.indexOf(token, token.length);
                const content = close > token.length ? tail.slice(token.length,close) : '';
                // Do not interpret word_internal_underscores as emphasis.
                const insideWord = token[0] === '_' && /[\p{L}\p{N}]/u.test(text[i-1] || '') && /[\p{L}\p{N}]/u.test(text[i+token.length] || '');
                if (!insideWord && content && !/^\s|\s$/.test(content) && !content.includes('\n')) {
                    out = node(doc, token === '~~' ? 'del' : token.length === 2 ? 'strong' : 'em');
                    inline(out,content,depth+1); consumed=close+token.length;
                }
            }
            if (out && out !== true) parent.append(out);
            if (!out) plain(token);
            last = i + consumed;
            specials.lastIndex = last;
        }
        plain(text.slice(last));
    }
    function cells(line) {
        const s = line.trim();
        const result = []; let cell = '', escaped = false;
        for (const c of s) {
            if (escaped) { cell += '\\' + c; escaped = false; }
            else if (c === '\\') escaped = true;
            else if (c === '|') { result.push(cell.trim()); cell = ''; }
            else cell += c;
        }
        if (escaped) cell += '\\';
        result.push(cell.trim());
        if (s.startsWith('|')) result.shift();
        if (s.endsWith('|') && result[result.length-1] === '') result.pop();
        return result;
    }
    const fence = /^( {0,3})(\x60{3,}|~{3,})([^\n]*)$/;
    const list = /^( {0,3})([-+*]|\d{1,9}[.)])([ \t]+)(.*)$/;
    const heading = /^ {0,3}(#{1,6})[ \t]+(.+)$/;
    const task = /^\[([ xX])\](?:[ \t]+|$)(.*)$/;
    function tableShape(lines, i) {
        if (i+1 >= lines.length || !lines[i].includes('|') || !lines[i+1].includes('|')) return null;
        const h = cells(lines[i]), sep = cells(lines[i+1]);
        return h.length >= 2 && h.length <= 32 && sep.length === h.length && sep.every(x=>/^:?-{3,}:?$/.test(x)) ? {h,sep} : null;
    }
    function render(container, source, options = {}) {
        if (!container) return;
        const doc = container.ownerDocument;
        source = String(source ?? '').replace(/\r\n?/g,'\n');
        container.replaceChildren();
        if (source.length > MAX_SOURCE || Array.from(source).length > 16000) {
            container.textContent = source; return;
        }
        const lines = source.split('\n'), offsets = []; let pos = 0;
        lines.forEach(l=>{offsets.push(pos);pos+=l.length+1;});
        const frag = doc.createDocumentFragment();
        let i = 0;
        while (i < lines.length) {
            const line = lines[i], open = fence.exec(line), h = heading.exec(line), li = list.exec(line);
            if (!line.trim()) { i++; continue; }
            if (open) {
                const code = []; const char = open[2][0], length = open[2].length;
                i++;
                while (i<lines.length) {
                    const end = fence.exec(lines[i]);
                    if (end && end[2][0]===char && end[2].length>=length && !end[3].trim()) { i++; break; }
                    code.push(lines[i++]);
                }
                const pre = node(doc,'pre'); pre.tabIndex=0;
                pre.append(node(doc,'code',code.join('\n'))); frag.append(pre); continue;
            }
            if (/^(?: {4}|\t)/.test(line)) {
                const code=[];
                while(i<lines.length && /^(?: {4}|\t)/.test(lines[i])) code.push(lines[i++].replace(/^(?: {4}|\t)/,''));
                const pre=node(doc,'pre');pre.tabIndex=0;pre.append(node(doc,'code',code.join('\n')));frag.append(pre);continue;
            }
            if (h) {
                const el = node(doc,'h'+h[1].length);
                inline(el,h[2].replace(/[ \t]+#+[ \t]*$/,''));frag.append(el);i++;continue;
            }
            const shape = tableShape(lines,i);
            if (shape) {
                const wrap=node(doc,'div');wrap.className='memo-md-table-scroll';wrap.tabIndex=0;wrap.setAttribute('aria-label','表');
                const table=node(doc,'table'),thead=node(doc,'thead'),tbody=node(doc,'tbody'),tr=node(doc,'tr');
                shape.h.forEach((v,j)=>{
                    const th=node(doc,'th');th.scope='col';inline(th,v);
                    th.className=shape.sep[j].startsWith(':')&&shape.sep[j].endsWith(':')?'memo-md-center':shape.sep[j].endsWith(':')?'memo-md-right':'';
                    tr.append(th);
                });
                thead.append(tr);table.append(thead,tbody);i+=2;
                while (i<lines.length && lines[i].trim() && lines[i].includes('|') && !fence.test(lines[i])) {
                    const row=cells(lines[i]);
                    if (row.length!==shape.h.length) break;
                    const r=node(doc,'tr');
                    row.forEach((v,j)=>{const td=node(doc,'td');td.className=tr.children[j].className;inline(td,v);r.append(td);});
                    tbody.append(r);i++;
                }
                wrap.append(table);frag.append(wrap);continue;
            }
            if (li) {
                const ordered=/^\d/.test(li[2]), block=node(doc,ordered?'ol':'ul');
                if(ordered) block.start=Number.parseInt(li[2],10);
                let m;
                while(i<lines.length && (m=list.exec(lines[i])) && /^\d/.test(m[2])===ordered) {
                    const item=node(doc,'li'), t=task.exec(m[4]);
                    if (t) {
                        item.className='memo-md-task-item';
                        const label=node(doc,'label'), input=node(doc,'input');
                        input.type='checkbox';input.className='memo-md-task';input.checked=t[1].toLowerCase()==='x';
                        input.disabled=options.interactive!==true;
                        input.dataset.memoTaskOffset=String(offsets[i]+m[1].length+m[2].length+m[3].length+1);
                        const content=node(doc,'span');inline(content,t[2]);label.append(input,content);item.append(label);
                    } else inline(item,m[4]);
                    block.append(item);i++;
                }
                frag.append(block);continue;
            }
            // Preserve visible line breaks without interpreting arbitrary HTML.
            const para=node(doc,'p');inline(para,line);i++;
            while(i<lines.length && lines[i].trim() && !fence.test(lines[i]) && !heading.test(lines[i])
                && !list.test(lines[i]) && !/^(?: {4}|\t)/.test(lines[i]) && !tableShape(lines,i)) {
                para.append(node(doc,'br'));inline(para,lines[i++]);
            }
            frag.append(para);
        }
        container.append(frag);
    }
    function toggleTask(source, offset, checked) {
        if (typeof source !== 'string' || !Number.isSafeInteger(offset) || offset<1 || offset>=source.length-1
            || source[offset-1]!=='[' || source[offset+1]!==']' || !/[ xX]/.test(source[offset])) return null;
        // Only a task actually rendered from this exact source may be toggled.
        // Reuse the block grammar rather than changing arbitrary bracket text.
        const lines=source.split('\n');let pos=0, activeFence=null;
        for (const line of lines) {
            const f=fence.exec(line);
            if(activeFence) {
                if(f && f[2][0]===activeFence[0] && f[2].length>=activeFence.length && !f[3].trim()) activeFence=null;
            } else if(f) activeFence=f[2];
            else {
                const m=list.exec(line);
                if(m && task.test(m[4]) && offset===pos+m[1].length+m[2].length+m[3].length+1) {
                    return source.slice(0,offset)+(checked?'x':' ')+source.slice(offset+1);
                }
            }
            pos+=line.length+1;
        }
        return null;
    }
    function renderCard(card) {
        if (!card) return;
        const body=card.querySelector('.memo-body');
        if (!body) return;
        const raw=card.getAttribute('data-memo-raw');
        if (raw===null) return;
        const markdown=card.getAttribute('data-memo-format')==='markdown';
        body.classList.toggle('memo-markdown',markdown);
        if(markdown) render(body,raw,{interactive:true});
        else body.textContent=raw;
    }
    function init(doc) {
        doc.querySelectorAll('[data-dashboard-widget-type="memo"]').forEach(renderCard);
        if(root.jQuery) root.jQuery(doc).on('iguguru:widget-card-refreshed.memoMarkdown',function(_,card){
            if(card && card.matches('[data-dashboard-widget-type="memo"]')) renderCard(card);
        });
    }
    const api={render,renderCard,toggleTask,safeUrl};
    if(typeof module!=='undefined' && module.exports) module.exports=api;
    if(root) {
        root.RssMemoMarkdown=api;
        if(root.document) {
            if(root.document.readyState==='loading') root.document.addEventListener('DOMContentLoaded',()=>init(root.document),{once:true});
            else init(root.document);
        }
    }
}(typeof window!=='undefined'?window:null));
