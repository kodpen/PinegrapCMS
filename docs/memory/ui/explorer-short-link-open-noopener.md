---
name: explorer-short-link-open-noopener
description: The File Manager opens a short link (open, Visit) with window.open(url, '_blank', 'noopener'), because its address redirects off the site
---

# Opening a short link from the File Manager

## How does view_folders.php open a short link?

`openItem()` (double click, Enter) and the `short_link_visit` menu entry
call `window.open(item.url, '_blank', 'noopener')`. `item.url` is the
link's own address on the site, but the server redirects it to the
destination a panel user typed, often on another site.

## Why noopener?

Without it the destination page gets `window.opener`, a handle on the
panel tab, and can send it to a look-alike login page (reverse
tabnabbing). `<a target="_blank">` gets noopener by default in browsers;
`window.open()` does not. Pages, files and ERP documents open the same way
without it: they stay on this site. Test:
`tests/explorer_short_link_noopener_test.php`.
