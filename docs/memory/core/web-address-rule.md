---
name: web-address-rule
description: Short link destination and auto dialog URL share pg_web_address_problem() on save; pg_address_opens_as_web() and its JS twin guard a stored address where it is printed
module: core
---

## Which addresses does the rule take?
- `pg_web_address_problem($url)` (`includes/fn/core.php`): '' for an http or https address or a path on this site; else the message. Refused: a space or control character ("Sorry, you may not enter a space in the URL."), any other scheme, a `//host` start, a backslash ("The address must be a web address or a path on this site."). Trimmed first; the caller checks that a value was given.
- `pg_short_link_url_problem()` and `pg_auto_dialog_url_problem()` call it, so both fields refuse the same addresses with the same Turkish text. A menu item has its own wider rule (`pg_menu_item_url_problem()`: also `mailto:` and `tel:`).

## Why is there a second check where the address is printed?
- A row saved before its field was checked can still hold `javascript:`. `pg_address_opens_as_web($url)` says whether a browser opens it with no scheme or with http/https, read as the URL parser reads it: controls and spaces around it dropped, tab/CR/LF dropped anywhere (`java<TAB>script:` is `javascript:`). `//host` and backslashes pass here: they keep the page's own scheme.
- Its browser twin is `software.is_web_address()` in `frontend.src.js` and the hand-patched `frontend.min.js`. Both were checked against the browser's URL parser on fuzzed input.

## Where are they used?
- Auto dialogs: the screens and the external API save through `pg_auto_dialog_url_problem()`; `get_page_content.php` passes the preview and the enabled rows through `pg_auto_dialogs_for_page()` (`includes/fn/auto_dialogs.php`); `software.open_dialog()` returns false before building the iframe for any other scheme.
- Symptom before (#9013): the auto dialog URL took `javascript:…`, and the iframe ran it in the site's origin on every page the dialog opened on.

## Tests?
- `tests/web_address_test.php` (both rules, the short link sharing them, both script twins), `tests/auto_dialog_form_test.php` (the field, the page guard, the API).
