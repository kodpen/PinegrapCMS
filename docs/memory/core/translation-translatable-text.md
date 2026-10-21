---
name: translation-translatable-text
description: Which values the front-end translation counts as text to translate (pg_tr_translatable()) and which it leaves out as data: tokens, codes, Lorem ipsum, data: URIs, JSON, lone addresses, code
---

## Where is the rule, and who uses it?
`pg_tr_translatable()` in `includes/translate/store.php`. Extraction (`content.php`, `extract.php`, the editor's save in `translations_action.php`) skips a value it rejects, so it is never stored, listed or sent to an engine. Rendering (`pg_tr_render_text()` in `render.php`) returns it as written and does not count it in `total`, so it cannot hold a page under the "reviewed" index policy (`total > hits` means noindex). One rule on both sides keeps hashes and counts in step.

## What is not translatable?
- Empty, or no letter once tags are stripped.
- Only `^^field^^` tokens or `{var:1}` placeholders.
- A code: one word of capitals and digits with a digit (`KT-1`, `M20/B`).
- Lorem ipsum (`pg_tr_placeholder_latin()`; owner decision, stays).
- Data values (`pg_tr_data_value()`, read on the raw value before `strip_tags()`):
  - a `data:` URI with its media type and comma (`data:image/svg+xml,<svg…>`, `data:image/png;base64,…`); "Data: 2024 report" is a sentence;
  - a JSON object or array: starts with `{` or `[` and `json_decode()` gives an array;
  - a lone address: `http(s)://…`, `ftp://…`, `//host.tld/…`, `www.host.tld…`, `mailto:`, `tel:`, or an e-mail address, with no other word around it (also when it is the text of its own link);
  - code, on unmistakable marks only: a `<script` or `<style` element, `function (…) {`, an arrow function with a block (`) => {`) or ending a statement with `;`, a CSS rule (`sel { prop: value; }`).

## What stays translatable?
Sentences with an address, an e-mail or a link in them, inline markup with `<a href>`, prices and numbers in words, a sentence around a `^^field^^` token, "[New] …" brackets that are not JSON, the word "function" in a sentence, `A => B` without a block or a closing `;`. Tests: `tests/translate_translatable_test.php`.

## Why were data values added?
On kodpen.com the tools form keeps an icon field (`data:image/svg+xml,<svg…>`) and a package field (`{"v":1,"uses":[…],"items":[…]}`) per record. Both were listed as pending texts (112 of them), the engines could not translate them, and the home page widget that prints the last three icons counted each as untranslated, so `/en/`, `/en/araclar` and `/en/arac/*` got `noindex` (issue #1094, PR #1171).

## What happens on a site that already stored such values?
- Pages: at once. `pg_tr_render_rules()` went to `2026.11.0`, so `pg_tr_render_rules_check()` drops the drawn bodies and kept verdicts once, and the next request counts without the data values.
- Store: the `translation_uses` rows stay until their owner is extracted again; `pg_tr_uses_rewrite()` / `pg_tr_store_owner_segments()` delete the owner's rows first, so the data values drop then. For form records: the Forms group (whole-site "Update translations" or the group's "Update these texts") or "Update this page" on a page whose list or detail widget includes the records. Saving a form record does not extract. The `translation_strings` rows (and any translation an engine gave) stay without a use: not counted, not listed, not loaded by `pg_tr_map()`.
- Not the same as the prune of #1018 (`pg_tr_uses_prune()`, `content-translation-uses.md`), which removes the uses of deleted or binned pages, not values inside a live owner.
