---
name: workspace-text-links
description: Where a web address in a Workspace text ends: escaped quotes, angle brackets and apostrophes stay outside the link, &amp; stays inside, punctuation after it stays text
---

## Where does an address end?
- `ws_format_text()` (includes/workspace/render.php) escapes with `h()` first and finds addresses in the escaped text. One address character is `ws_text_address_char()`: no space, `<`, `>`, `"`, set-aside sign `\x01`, and of the entities only `&amp;`. So the address ends where `&quot;`, `&#039;`, `&lt;` or `&gt;` starts.
- The same piece is used by the `[text](address)` rule (with `()` excluded too) and by the guest page (`ws_guest_render()`, guests.php).
- Pinned in `tests/workspace_render_test.php`: double and single quotes, `<…>`, a JSON value, `'de` / `'da` / `'a` / `'e` suffixes, `&amp;` in a query, the plain and trailing-dot cases.

## What stays outside the link after it?
- `ws_text_address_split()`: trailing `. , ; : ! ? )` and a trailing `&amp;` whole (it used to leave `&amp` in the link and `;` outside).

## What did it fix?
- #3366 (PR #3468): the old class `[^\s<>"]` ran over the entities, then `rtrim()` cut the `;`: `"https://a.test"` linked to `…%22` with a stray `;`, `<…>` to `…&gt`, a JSON value took `"}}`, and `https://kodpen.com'da` linked to a broken host. The most common case in Turkish is the apostrophe suffix.
- An href never carries a raw quote: the escaped text is reused as before.

## Is there a JavaScript twin?
- The writing box (`workspace_editor.js`) reads the raw text: `inlineNodes()` reads `[text](address)` with `[^\s()<>"']`, `PASTE_LINK` (a pasted address alone) the same class, and `cleanUrl()` (the link tip) refuses `\s<>"'`. So `[x](https://kodpen.com'da)` stays text in the box as on the server, and `[x](https://kodpen.com)'da` links only the address. `&` in a query stays inside.
- The box links no bare address; only the server does.
- Pinned in `tests/workspace_editor_links_test.php` (run under Node against `ws_format_text()`). Before the follow-up to #3366 the class was `[^\s()<>"]` and let the apostrophe in.
