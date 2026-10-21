---
name: content-translation-result-check
description: pg_tr_result_check(): when a translation of a site text is refused or marked suspicious, and why a brand name the glossary keeps is not suspicious
---

## When is an answer refused?
`pg_tr_result_check($source, $translated, $format, $keep_terms)` (`includes/translate/engines.php`) refuses an empty answer (`empty`), one whose `^^field^^` / `{var:N}` tokens differ from the source (`placeholders`) and, for an inline text, one without the same tags the same number of times (`markup`). Every writer calls it after `pg_tr_sanitize_incoming()`: `pg_tr_accept_result()` (Google, Pinegrap AI, Claude, browser engines), the Translations screen's save (`translations_action.php`), the external API write (`translate_api_write_one()`) and the CSV import (`pg_tr_csv_import()`).

## When is it marked suspicious?
A length out of proportion (shorter than a fifth or longer than five times the source, sources over 3 characters), or an answer that is the source word for word while the source had something to translate. "Something to translate" is a run of `pg_tr_runs_split()` with the language's keep terms (`pg_tr_keep_terms($language)`, glossary rows marked "keep as it is"). A suspicious translation shows in the Suspicious filter and is left out of the default "Approve all".

## Why are the keep terms passed?
Before #850 the check split the source without the glossary: a text that is only a kept brand name ("Pinegrap" in a menu or a button) was never sent to an engine (no runs), came back as itself and was still marked suspicious by every engine, so each one had to be approved by hand. Every caller now passes `pg_tr_keep_terms($language)`; without them (the default) the old rule applies. Translations flagged before the fix keep their flag until written again.

## Where is it tested?
`tests/translate_result_check_test.php`.
