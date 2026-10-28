---
name: loop-band-canvas
description: The loop area's band on the canvas (.sd-loop-band): one line with an ellipsis and the full text in its tooltip, and the Turkish terms the band and its notes use (#2523)
---

## Why does the band stay on one line?
- The band is an absolutely positioned label over the loop area (`buildInnerElement` in `style_designer.js`, the inline canvas CSS). In a narrow loop area (a 156 px ticker at the XS preview, a sidebar list) its text wrapped into four lines and lay over the area's content, and the ghost-record note ("· 2 real records after the card") fell into the middle of it (#2523). The band now carries `white-space: nowrap; overflow: hidden`, its text span `min-width: 0; overflow: hidden; text-overflow: ellipsis`, and the note `flex: none`; the band's `title` holds the whole text (set when the band is built and again when the note is appended), so a cut band still reads in full on hover. The table-row variant (`.sd-loop-band-inner`) already clipped.

## Which Turkish terms do the band and its notes use?
- One word per term, set with `tools/tr_add.php --replace`: loop area → "tekrar alanı", at runtime → "yayında", template → "şablon", canvas → "tuval", rendered → "çizilir / çizilmez". The band ("Tekrar alanı — 1 kayıt gösteriliyor (yayında her kayıt için yinelenir)"), the row band, the two drop hints ("Yinelenen şablonu buraya sürükleyin …") and the three "this widget …" notes under a loop that does not run follow it. Keep new designer texts on these terms.
