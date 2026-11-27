---
name: office-docx-links
description: A link on selected .docx text added, changed or taken off (office_docx_link.php, DA3): the bar's Link button and window, the save's link stretch, hyperlink and relationship written, the marks' address
module: office_docx_link
---

## How is a link put on text?
- The document bar's "Link" `pg-icon-button` (after Highlight color) or Ctrl/Cmd+K opens a small window under it: "Address", one line of help, "Remove link" (only on a link), Cancel, Apply. Enter applies, Esc closes and puts the selection back. Each Apply or Remove is one change of Undo.
- It acts on the selected text, or with only a caret on the whole link the caret stands in; with neither the status line says "Select text in a paragraph to link it." The button is pressed when the selection or caret is in a link.
- A click on a link in the page puts the caret there and opens the same window; the address is never opened from the page (Ctrl+click no longer follows it).
- The window writes the address as a save sends it (`linkAddress()`): `ayse@example.com` becomes `mailto:`, `+90 555 …` a `tel:` without spaces, `example.com` `https://`, a space in a web address `%20`. Anything else, `#…` typed included, shows "Write a web address starting with http:// or https://, an e-mail address or a phone number."

## How does the editor hold a link?
- As a part of a character's look, beside colour, highlight and size (`DOC_LOOK_FORMATS` key `l`, `office-docx-bar-look.md`), so Enter, joins, Undo and `formatRanges()` carry it. Text newly linked also takes colour `0563C1` and underline; text leaving a link loses them when it has exactly those.
- Text typed right after a link goes on outside it (`carryMarks()`), as the server writes it into the run after the link; typed inside a link it stays in it. `drawText()` draws a link as `span.office-link` with the address as its title.

## What does a save send and the server write?
- A `format` stretch `set: {link: address | false}` (`office-docx-format.md`). Allowed (`pg_office_docx_link_value()`): http or https with a host, `mailto:` with an @, `tel:`, or `#` and a bookmark name (only carried over from the file); at most 2048 characters, no white space or control character; anything else is the `none` refusal.
- `pg_office_docx_paragraph_link()`: the stretch is first taken out of every link it reaches: the part of a link before it and after it stay links (a copy of the same hyperlink), its runs leave with `rStyle` Hyperlink or FollowedHyperlink taken off.
- Then, with an address, the runs from the first to the last (bookmarks between too) go into a new `w:hyperlink`: `r:id` of a new external relationship in `word/_rels/document.xml.rels` (next free `rIdN`, one per address a save) and `w:history="1"`, or `w:anchor` for a bookmark. A changed link is a new hyperlink; old relationships stay.
- The rels part is read only when a save holds a link and is written as one more part of the package; a rels part that cannot be read refuses the save as `unusual`. Added paragraphs take links too.

## What does the reader send?
- `edit.marks` third item has `l`: the address of the link a run stands in (relationship target, with `#anchor` when both), or `#name` for a bookmark link (`pg_office_docx_run_link()`); a link whose address is not one allowed above has none. The preview draws `tel:` links beside http(s) and mailto.

## What was checked?
- `tests/office_docx_link_test.php`: addresses, marks, wrap, cut in two, change, anchor, a save read back with an untouched paragraph byte for byte and the relationships, the editor's `linkAddress()` and the look in Node.
- Sandbox, Chromium, 1440 and 390 px: File Manager → New document and a channel's New document: type, select, Ctrl+K and the button, a refused address, Apply, Save, open again, click the link (window with its address, page not left), Remove link, Undo. Not opened in Word or LibreOffice.

Related: docs/memory/platform/office-docx-bar-look.md, docs/memory/platform/office-docx-format.md
