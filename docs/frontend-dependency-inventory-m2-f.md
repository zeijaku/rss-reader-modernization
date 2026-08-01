# Frontend dependency inventory — M2-F / R1

| Dependency | M2-E / R2 | M2-F / R1 | 判断 |
|---|---:|---:|---|
| jQuery | 3.3.1 | 3.7.1 | full buildへ更新。既存 `$.ajax` を維持 |
| Font Awesome Free | 5.3.1 | 6.7.2 | LTS系へ更新。旧class aliasを維持 |
| Bootstrap CSS / JS | 4.1.3 | 4.1.3 | 据え置き |
| Bootswatch 7 themes | 4.1.3 | 4.1.3 | Bootstrapとの組合せを維持 |
| Popper | 1系 | 1系 | Bootstrap 4.1.3との組合せを維持 |
| jquery-drawer | 3.2.2 | 3.2.2 | 据え置き |
| iScroll | 5.2.0-snapshot | 5.2.0-snapshot | 据え置き |

## 判断

この工程では、Version番号を揃えるためだけの一括更新は行わない。Bootstrap 5へ移る場合、`data-toggle` / `data-dismiss`、jQuery plugin呼出し、Popper、Drawer、Bootswatch 8テーマをまとめて変更する必要がある。M2-Fへ混ぜると画面改修の範囲が大きくなり、依存更新だけの差分ではなくなるため保留した。

jQueryは3系の範囲で更新し、slim buildではなくAJAXを含むfull buildを採用した。Font Awesomeは既存の `fas` / `far` / `fab` と `fa-pencil-alt` 等のaliasが残る6.7.2を使用している。

## 更新Asset

- `public/js/jquery-3.7.1.min.js`
- `public/css/all.css`
- `public/webfonts/fa-*.ttf`
- `public/webfonts/fa-*.woff2`

## 削除Asset

- `public/js/jquery-3.3.1.min.js`
- Font Awesome 5.3.1のEOT / SVG / WOFFと旧font binary

LibraryはProject内へ同梱し、CDN、npm、build toolは追加していない。
