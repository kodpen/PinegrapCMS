---
name: prototype-canvas-features-3
description: Plan prototype-canvas, features F26 to F34 from the owner's answer of 2026-11-16: empty links and unreached pages marked, a target suggested for empty links and written only when he applies
---

## Which further problems are found?
- (F26) An empty link: its address is empty or `#` alone. Not one: `#name`, a link that only opens or closes something on its page (the read's `toggle`), a bound link. Its mark is `empty`, amber: the rectangle on the picture, its row in the list saying "No target", no arrow. Today it reads as an anchor.
- (F27) A page nothing reaches: no link of another page or of a shared part leads to its frame (`G.check()`, PV1). Never one: the Start page (F2), a shared part, a chip. Its head carries an amber badge "No link leads here", readable when dimmed.
- (F28) After the red "N broken" (F19) the bar says "N to check" in amber: empty links plus unreached pages. The problems list opens in three groups, an empty group left out: "Broken links", "Pages no link leads to", "Links without a target". A row selects its frame, and its link when it has one.
- (F29) The head's red count (F20) stays broken links only; an amber count beside it is that frame's empty links.

## How does an empty link get its target?
- (F30) While the design has empty links the bar shows "Suggest targets · n". It opens a dialog over the canvas with one row per empty link, grouped by page: the link's label, a target selector, a tick. A link in a shared part is one row.
- (F31) The match is pure (`PGProtoHotspots.suggest(link, pages)`). Label, page name and page title are folded: lower case, Turkish letters to their plain ones, punctuation to spaces, filler words dropped (one short English and Turkish list in a constant: go, to, the, page, view, see, git, sayfa, gör, incele).
  - Sure: the folded label equals a page's name or title; or every word of one page's name begins a word of the label (three letters or more, so "Sepete git" finds Sepet) and no other page does.
  - Unsure: several pages match that way, or only some words do; a label that matches nothing is tried once more with the nearest heading above it in its block, and what that finds is unsure. At most three candidates, best first.
  - The link's own page is never a candidate; nor a page in the bin.
- (F32) A sure row is ticked with its page selected. An unsure row is not ticked: it asks "Where should "label" go?" with its candidates as buttons and "Skip"; a button selects that page and ticks the row. A row with no candidate has the selector on "Choose a page" and no tick. The head counts "n sure · n to ask · n without a suggestion". A click on a label selects that link on the canvas behind.
- (F33) While the dialog is open the ticked rows of the selected frame are drawn as dashed neutral arrows from the mark to the chosen frame; a tick taken away takes its arrow away.
- (F34) Nothing is written before "Apply n". Cancel and Escape write nothing; a skipped row is not remembered and is suggested again next time.
  - Apply writes each ticked row as a drawn arrow is written (F21, K1 to K4): the tab opened, the lock, `_sdMayEdit()`; the shared part and live page warnings asked once for the whole batch, naming the parts and pages it touches.
  - One undo entry per tab, so undo on a tab takes back that tab's batch.
  - A row that could not be written (locked by someone else, no right) stays in the dialog with its reason; then a toast "n links connected" and the links are read again.
- Never touched: a link that has a target, a broken link (its typed address is kept, "Change target" is its way), a toggle.
