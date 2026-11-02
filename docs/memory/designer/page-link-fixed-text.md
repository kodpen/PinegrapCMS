---
name: page-link-fixed-text
description: A value of a link to another page that is fixed text, the same in every record ({name, text} in _bindParams.href.params): spec, address, list rows, the panel's Fixed text row
---

## What is it for?
- A link that opens a form with an answer chosen: the bookshop's Reserve a seat sends `?topic=Reserving a seat at an event` beside `?event=<title>` (#4808). A record field cannot hold a value every record shares.

## How is it stored and read?
- A param `{name, text}` in `_bindParams.href.params`, beside `{name, token}` rows ([[record-value-page-links]]).
- `pg_sw_page_link_spec()`: text is read only when the token is empty, trimmed; blank or not a string, the row is dropped. A token wins over text. Names follow the same rules (refused, repeated, at most 8).
- `pg_sw_page_link_values()`: the text is the value, also with no record (the bare page still gets it); `pg_sw_page_link_url()` encodes it once and leaves it out when `pg_sw_url_param_value()` would not read it back (over 100 characters, several lines).
- A text param has no `token`: `pg_sw_page_link_tokens()` asks the record query nothing for it, and in a list inside the record it is never a row value (no `row` flag); a deferred row link carries it as the record's value.

## How does the editor show it?
- `_swPageLinkSpec()` keeps `text` (a string, even empty) while the token is empty, so the row stays a text row; choosing a field drops it.
- `_sdPageLinkRows()`: the value select's "Fixed text" option (value `:text`, no token has a colon) and a text box `sd-sw-plink-text` under the row (100 characters, one undo step per run of keystrokes).
- `_swPageLinkRowSends()`: a text row counts for the repeated-name warning only when its text is not blank, as on the server.

## How is it tested?
- `tests/designer_page_link_test.php` (`…_fixed_text`): spec, bindings in a sourced list, values, the editor twin and panel wiring; `design_template_bookshop_events_test.php`: the bookshop's link and the contact fields that read it.
