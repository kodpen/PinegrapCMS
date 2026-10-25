---
name: comments-widget-email-design
description: Designing the comments widget for a comment e-mail page: the E-mail canvas state, the link to the comment the card must keep, the canvas note without it, mail-safe styles, save warnings
---

How the e-mail draws the widget: [[comments-widget-email]].

## What does the canvas show for an e-mail?
- Canvas state E-mail (chip, Canvas shows): facts `email`, count 1; hides what the e-mail strips (`_sdCommentsEmailDrops()`, twin of `pg_comments_email_drops()`), no sample comments; a dropped node picked moves to another state. `is_email` is in Show only when.
- `.sd-wrap.sd-state-off:not(#sd-state-shown)`: an id's weight; with two classes a hidden node in a `d-flex` row (display:contents) still showed.

## Which card does an e-mail draw?
- The server keeps its draw only when the comment carries a link to itself (`pg_comments_email_drew()`); else the widget prints nothing and the fixed markup goes out.
- A link to the comment: the Share control on a link, an `<a>` or a button component drawn as a link (a `<button>` is stripped); a link whose address is bound to `__comment_url` (no format after it) or reads `^^__comment_url^^`; a content node's markup with `href="^^__comment_url^^"`.
- Lost below what the e-mail strips, a comments section, another control or small form, a thread flag false in an e-mail, or a comment flag other than `comment_is_root` (it holds for some comments only, or for none: `comment_is_reply`, `comment_has_replies`).
- The list is lost below a false thread flag, a comment flag, a comments section or the share, reply, watch or open-or-close control. No loop area, an empty one, or one inside a stripped form: the server's default card, which has the Share link.

## What does the canvas do without that link?
- `_sdCommentsEmailDrawsComment(root, flags)` answers as the server does (`test_comments_widget_email_draws_comment_twin`: a table of trees drawn by both). In the E-mail state `_sdCommentsFlagOff()` then leaves the whole widget out (its root, `_sysWidgetRenderTreeRoot`), as the e-mail does, and `_sdAppendCommentsEmailNote()` puts a note in its place (`sd-system-empty-hint`, skipped by the canvas checks). `_sdCommentsStateFor()` moves a picked node to a state that draws it.
- No save warning for it: the E-mail state and its note show it.

## What should a design do?
- Make a copy for this page for the e-mail page: inline colours with a `var()` fallback, words under `is_email`; mail clients may drop the stylesheet.
- Keep a link to the comment that every comment shows: the starter's Share link (a link, not a button), or a link bound to `__comment_url`.

## Which save warnings does an e-mail page get?
- Only `widgets` ([[comments-widget-save-warnings]]): a page another page names as its comment e-mail page (`pg_comments_is_email_page()`, asked once a widget is found) draws no live thread.

## How was it checked?
- `tests/comments_widget_email_test.php` (the canvas twin against the server's draw), `tests/comments_widget_designer_test.php` (E-mail state, the widget left out, picks, the note's wiring). Sandbox (2026-10-25): the E-mail chip in Chromium; the note and the widget left out: Node tests only.
