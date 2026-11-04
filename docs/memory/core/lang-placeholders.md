---
name: lang-placeholders
description: How lang() fills {var:N}, its case modifiers and {suffix}: one strtr() pass, values never re-scanned, plain {var} left for JavaScript
module: lang
---

## Which placeholders does lang() fill?
- `{var:N}` the Nth value (`'vars'`, a scalar is the first); `{var:N|c}`, `|u`, `|l` (also written `{var|N|c}`) title, upper or lower case; `{var|c}`, `{var|u}`, `{var|l}` the first value; `{suffix:N}` and `{suffix}` (the first suffix) from `'suffix'`.
- Plain `{var}` / `{var2}` is not filled: those keys are JavaScript templates the browser fills with `.replace('{var}', …)` (`translations.php`, `view_folders.php`).
- A placeholder with no value stays in the text.

## Can a value carry placeholder text into the output?
- No. All placeholders are filled in one `strtr()` pass (#6045), so a value such as a page name `{var:2}` or `a{suffix}` is printed as typed. Before, each value was put in with `str_replace()` one after another and a later value or the suffix filled the placeholder text inside an earlier value.

## Does a modifier change the plain placeholder of the same value?
- No. `{var:1|c} … {var:1}` title-cases only the first; before, every `{var:1}` took the case of the first modifier found, and a second modifier of the same value stayed unfilled.

## Does lang() escape the values?
- No: `output-escaping.md`.
