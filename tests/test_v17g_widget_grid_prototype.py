from __future__ import annotations
from html.parser import HTMLParser
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
BASE = ROOT / 'docs' / 'prototypes' / 'v1-7-g'
HTML = BASE / 'widget-grid-prototype.html'
CSS = BASE / 'widget-grid-prototype.css'
JS = BASE / 'widget-grid-prototype.js'
failures=[]
def check(value, message):
    print(('PASS' if value else 'FAIL') + ': ' + message)
    if not value: failures.append(message)

class Parser(HTMLParser):
    def __init__(self):
        super().__init__(); self.widgets=[]; self.ids=[]; self.themes=[]
    def handle_starttag(self, tag, attrs):
        a=dict(attrs)
        if 'id' in a: self.ids.append(a['id'])
        if tag=='article' and 'prototype-widget' in a.get('class','').split(): self.widgets.append(a)
        if tag=='option' and a.get('value'): self.themes.append(a['value'])

html=HTML.read_text(encoding='utf-8'); css=CSS.read_text(encoding='utf-8'); js=JS.read_text(encoding='utf-8')
p=Parser(); p.feed(html)
check(len(p.widgets)==9, 'fixture includes nine representative Widgets')
check(len(p.ids)==len(set(p.ids)), 'prototype element ids are unique')
check([w.get('data-height') for w in p.widgets].count('2')==1, 'fixture includes exactly one vertical span-2 Widget')
check(p.widgets[3].get('id')=='widget-tall' and p.widgets[3].get('data-width')=='1', 'fourth Desktop Widget is 1x2')
check(set(p.themes)=={'bootstrap','bootstrap-minty','bootstrap-lux','bootstrap-sketchy','bootstrap-darkly','bootstrap-superhero','bootstrap-solar','bootstrap-slate'}, 'all eight themes are selectable')
check('grid-template-columns: repeat(4' in css and 'grid-template-columns: repeat(2' in css, 'Desktop four-column and Tablet two-column rules exist')
check('display: flex; flex-direction: column' in css and '@media (max-width: 767.98px)' in css, 'Smartphone uses one-column flow')
check('[data-height="2"] { grid-row: span 2; }' in css, 'vertical span-2 rule exists')
check('grid-auto-rows: var(--prototype-row-height)' in css, 'fixed-row comparison mode exists')
check('grid-auto-rows: minmax(var(--prototype-row-height), auto)' in css, 'content-priority comparison mode exists')
check('overflow: auto' in css and 'overscroll-behavior: contain' in css, 'fixed-row mode contains long body content')
check('orderAfterKey' in js and 'ArrowLeft' in js and 'Home' in js and 'End' in js, 'keyboard reorder prototype exists')
check('grid-auto-flow: dense' not in css, 'dense packing is not used, preserving DOM order')

# Deterministic occupancy simulation for the requested Desktop layout.
def place(widgets, cols):
    occupied={}; placed=[]
    for w in widgets:
        width=min(int(w.get('data-width','1')), cols)
        height=int(w.get('data-height','1'))
        row=1
        while True:
            found=None
            for col in range(1, cols-width+2):
                cells=[(r,c) for r in range(row,row+height) for c in range(col,col+width)]
                if all(cell not in occupied for cell in cells): found=(row,col,cells); break
            if found: break
            row+=1
        for cell in found[2]: occupied[cell]=w['id']
        placed.append((w['id'],found[0],found[1],width,height))
    return occupied, placed
occ4, placed4=place(p.widgets,4)
check(occ4[(1,4)]=='widget-tall' and occ4[(2,4)]=='widget-tall', 'Desktop rightmost Widget occupies row 1 and row 2')
check([occ4[(2,c)] for c in (1,2,3)]==['widget-memo','widget-calendar','widget-icon'], 'Desktop row 2 has three Widgets beside the lower half')
occ2,_=place(p.widgets,2)
check(all(c in (1,2) for _,c in occ2), 'Tablet placement stays within two columns')
check('width: 100%; height: auto; min-height: 0' in css, 'Smartphone cancels artificial vertical span height')

if failures: raise SystemExit(1)
print('All V1.7-G prototype checks passed.')
