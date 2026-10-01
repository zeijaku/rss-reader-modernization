from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
s = (ROOT/'public/settings.php').read_text()
j = (ROOT/'public/js/settings-tabs.js').read_text()
d = (ROOT/'public/js/drawer-categories.js').read_text()
c = (ROOT/'public/css/dashboard.css').read_text()
checks = []
def check(ok, label):
    checks.append(bool(ok)); print(('PASS' if ok else 'FAIL')+': '+label)
for pane, tab in [('display','Display'),('tabs','Tabs'),('links','Links'),('highlight','Highlight')]:
    check(s.count('id="'+pane+'"')==1, 'unique stable settings anchor: '+pane)
    check(s.count('id="settings'+tab+'Tab"')==1 and 'aria-controls="'+pane+'"' in s, 'accessible tab/pane link: '+pane)
for form in ['settingsForm','tabsForm','rssHighlightKeywordForm']:
    check(s.count('id="'+form+'"')==1, 'existing form retained once: '+form)
check(s.index('id="settingsForm"') < s.index('id="display"') < s.index('id="links"') < s.index('id="tabsForm"'), 'display and links retain one settings save form')
check(s.count('表示・リンク設定を保存')==2, 'both save buttons disclose combined save scope')
check("app_asset_url('js/settings-tabs.js')" in s, 'settings-only script inherits centralized asset revision')
for page in ['index.php','stock.php','file-library.php','remote-files.php','rss-management.php']:
    check('js/settings-tabs.js' not in (ROOT/'public'/page).read_text(), 'settings-only asset omitted from '+page)
check('hashchange' in j and 'pushState' in j and 'decodeURIComponent' in j, 'deep links and history changes are supported')
check('getElementById(hash)' in j and 'content.contains(target)' in j, 'hash lookup stays within settings content without selector interpolation')
check('innerHTML' not in j and '.ajax' not in j and 'fetch(' not in j, 'tab controller introduces no HTML rendering or API mutations')
check('#settingsPageTabs' in c and 'repeat(2, minmax(0, 1fr))' in c and 'min-height: 44px' in c[c.index('/* Settings page only:'):], 'responsive settings-scoped layout keeps touch targets')
check("'settings': ['./rss-management', './settings']" in d and "not('.drawer-mobile-links')" in d, 'one Settings entry preserves user links and RSS management')
print(f'RESULT: PASS {sum(checks)} / FAIL {len(checks)-sum(checks)} / SKIP 0')
raise SystemExit(0 if all(checks) else 1)
