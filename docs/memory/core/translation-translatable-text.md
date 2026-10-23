---
name: translation-translatable-text
description: What pg_tr_translatable() counts as text to translate and what it leaves out as data (tokens, codes, Lorem ipsum, data: URIs, JSON, whole-value code); lone addresses stay
---

## Where is the rule, and who uses it?
`pg_tr_translatable()` in `includes/translate/store.php`. Extraction (`content.php`, `extract.php`, the editor's save) skips a value it rejects: never stored, listed or sent to an engine. Rendering (`pg_tr_render_text()` in `render.php`) returns it as written before any lookup and does not count it in `total`, so it cannot hold a page under the "reviewed" index policy (`total > hits` means noindex). One rule on both sides keeps hashes and counts in step.

## What is not translatable?
- Empty, or no letter once tags are stripped.
- Only `^^field^^` tokens or `{var:1}` placeholders.
- A code: one word of capitals and digits with a digit (`KT-1`, `M20/B`).
- Lorem ipsum (`pg_tr_placeholder_latin()`; owner decision, stays).
- Data values (`pg_tr_data_value()`, read on the raw value before `strip_tags()`):
  - a `data:` URI with its media type and comma (`data:image/svg+xml,<svg…>`, `data:image/png;base64,…`); "Data: 2024 report" is a sentence;
  - a JSON object or array: starts with `{` or `[` and `json_decode()` gives an array;
  - code that makes up the whole value, each pattern anchored at both ends: a `<script>` / `<style>` element opening and closing the value, a function statement, an arrow function assigned to a name, a call whose argument is a function or an arrow with a block, CSS rules with an ASCII selector and ASCII property names.

## What stays translatable?
Sentences with an address, an e-mail or a link, inline `<a href>`, prices, a sentence around a `^^field^^` token, "[New] …" brackets, the word "function" or `A => B` in a sentence. Also a sentence with inline `<code>` / `<kbd>` (inline tags: the paragraph arrives as one value), a heading that names `<script>` or `<style>` (also a custom HTML block's decoded `&lt;script&gt;`), a text with a `{var:1}` placeholder ("Showing {var:1} of {var:2}" would otherwise read as CSS), and braces around non-ASCII words. Tests: `tests/translate_translatable_test.php`.

## Why are lone addresses not data?
A URL or an e-mail address on its own can carry a per-language value (a support mailbox, a localized page). Rendering returns a non-translatable value before the lookup, re-extraction drops its use, `pg_tr_map()` loads only strings with a use and the editor refuses to save it, so a reviewed address translation would stop being drawn with no way back. Since 64175f77 an engine answer identical to the source no longer counts as machine output, so a lone address weighs on the index verdict only until an engine has run.

## Why were data values added?
On kodpen.com the tools form keeps an icon field (`data:image/svg+xml,<svg…>`) and a package field (`{"v":1,"uses":[…],"items":[…]}`) per record. Both were listed as pending texts (112 of them), the engines could not translate them, and the home page widget that prints the last three icons counted each as untranslated, so `/en/`, `/en/araclar` and `/en/arac/*` got `noindex` (issue #1094, PR #1171).

## What happens on a site that already stored such values?
- Pages: at once. `pg_tr_render_rules()` went to `2026.11.0`, so `pg_tr_render_rules_check()` drops the drawn bodies and the kept robots answers once, and the next request counts without the data values.
- Store: the `translation_uses` rows stay until their owner is extracted again; the owner's rows are deleted first, so the data values drop then. For form records: the Forms group (whole-site "Update translations" or the group's "Update these texts") or "Update this page" on a page whose list or detail widget includes the records. Saving a form record does not extract. The `translation_strings` rows stay without a use: not counted, not listed, not loaded by `pg_tr_map()`.
- Not the prune of #1018 (`pg_tr_uses_prune()`, deleted or binned pages).
