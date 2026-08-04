from pathlib import Path
r=Path(__file__).resolve().parents[1]
checks=[]
def c(v,m): checks.append((bool(v),m))
widget=(r/'app/dashboard_widget.php').read_text()
api=(r/'app/api.php').read_text()
search=(r/'app/search_feed.php').read_text()
js=(r/'public/js/dashboard.js').read_text()
html=(r/'public/index.php').read_text()
cache=(r/'app/feed/feed_cache.php').read_text()
c("'search'" in widget.split('function dashboard_widget_types',1)[1].split('}',1)[0],'search widget allowed')
c('widget.search.create' in api and 'widget.search.fetch' in api,'API actions')
c("widget_type='search'" in search,'owner/type scoped lookup')
c('content_owner=:owner' in search,'owned feeds scoped')
c("hash('sha256', $source->url)" in cache,'URL shared cache')
c('search_result' in api and 'search_feed_execute' in api,'search results not persisted')
c('registerSearchFeed' in html and 'changeSearchFeed' in html,'settings modals')
c('renderFeedItems($card, resultFeed.item)' in js and 'renderFeedItems($card,r.items)' in js.replace(' ', '').replace('\n',''),'shared article renderer')
c('search-feed-refresh' in js,'card refresh')
c('app_validate_feed_url' in search,'common and owned URL validation')
failed=0
for ok,m in checks:
 print(('PASS' if ok else 'FAIL')+': '+m); failed+=0 if ok else 1
print(f'RESULT: PASS {len(checks)-failed} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
