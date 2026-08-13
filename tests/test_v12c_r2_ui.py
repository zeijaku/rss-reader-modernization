from pathlib import Path

from dashboard_source_utils import dashboard_source
root = Path(__file__).resolve().parents[1]
index = dashboard_source(root)
js = (root / 'public/js/dashboard.js').read_text()

checks = []

def check(value, message):
    ok = bool(value)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

form_start = index.index('function search_feed_form_fields')
form_end = index.index('<!-- Navbar -->', form_start)
form = index[form_start:form_end]

check('1/4' not in form and '1/2' not in form and '3/4' not in form,
      'Search Feed width no longer uses fraction labels')
check('<option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option>' in form,
      'Search Feed width labels match existing Widgets')
expected_styles = ('<option value="success">success</option><option value="primary">primary</option>'
                   '<option value="info">info</option><option value="secondary" selected>secondary</option>'
                   '<option value="dark">dark</option><option value="warning">warning</option>'
                   '<option value="danger">danger</option>')
check(expected_styles in form, 'Search Feed theme options match existing Widgets and include danger')
check('>Gray<' not in form and '>Blue<' not in form and '>Green<' not in form and '>Cyan<' not in form and '>Yellow<' not in form,
      'Fixed English color names were removed')
check('function renderSearchFeedTitle($card)' in js, 'Search Feed title restore helper exists')
check(".attr('data-search-query')" in js and ".text(viewTitle)" in js,
      'Search Feed title is restored from the saved search query as text')
fetch_start = js.index('function fetchSearchFeed')
fetch_end = js.index('function bindEvents', fetch_start)
fetch = js[fetch_start:fetch_end]
check('renderSearchFeedTitle($card); renderFeedItems($card,r.items);' in fetch,
      'Successful initial fetch restores title before rendering results')
check("if(!apiResponseOk(d)){if(!preserve)renderFeedError($card,'検索結果を取得出来ませんでした');return;}" in fetch,
      'Structured API error cannot leave the initial header in loading state')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
