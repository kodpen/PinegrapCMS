---
name: api-docs-conventions
description: The Conventions section and the heading outline of the API reference (api_docs.php and integration.php/docs): one renderer, what each topic states, how to keep it true when a rule changes
---

## Where is the Conventions section written?
- Once, in `includes/api/console_view.php`: `api_console_conventions($base_url)` returns the topics (id, title, text, optional example), `api_console_conventions_html($items)` draws them. The panel's API Documentation screen (`api_docs.php`) and the console (`integration.php/docs`, `api_console_page()`) print the same call above the endpoint list, so the console shows it before a key is entered.
- The topics, in order: Credentials, Errors and X-Request-Id, Paging, Idempotency-Key, Dry run, ETag and If-Match, Rate limit, POST in place of PATCH and DELETE, Formats, Language. Each block has an id `conv_<id>`; the section is `#conventions`, and the index opens with a Conventions link.
- A translated line under the heading says the reference is in English: route summaries, descriptions and field names come from `api_schema()` untranslated.

## What does each topic promise?
- Only what the code answers now (`response.php`, `fault.php`, `idempotency.php`, `dry_run.php`, `ratelimit.php`, `router.php`, `auth.php`). Examples: If-Match is checked right before the write with no lock (`api-if-match.md`); the message is in the site's language (`api-meta-language-timezone.md`).
- A change to one of those rules changes its sentence in `api_console_conventions()` in the same PR, with its Turkish through `tools/tr_add.php`. `tests/api_console_conventions_test.php` pins the topic list, the headers each names, and that no unbuilt rule is promised (`Accept-Language`, `error.errors`, `Location`, `HEAD`): a PR that builds one removes it from that list.
- The figures and names the text spells are held against the code by `test_api_console_conventions_figures_follow_the_code()`: the Idempotency-Key length cap and pending `Retry-After` (`idempotency.php`), the day a record lives (`maintenance.php`), the default 120 a minute (`keys.php` insert and the column default), the statuses that carry `docs` (`api_fail()`), each error code's status in `api_error_catalogue()`, each header in the file that reads or sends it, the envelope fields.
- None of those figures has a constant or function today, so the test reads the literal; a change there fails until the sentence follows.

## Which headings do the pages have?
- Page `h1` (the panel's heading, the console's site title), `h2` Conventions with an `h3` per topic, `h2.doc-group-h` per endpoint group (id `group_<prefix>`, plus `group_events`, `group_errors`), `h3.doc-ep-head` per endpoint and for the event and error blocks. Before, groups and endpoints were `div`s and the panel page had no heading below its title.
- The panel page adds `scroll-margin-top` under the fixed navbar so an index link lands below it.

## What moved out of the pages?
- The one-paragraph summary (HTTP Basic, minor units, ISO-8601, cursor paging, X-Dry-Run) on both pages, the console's DELETE alias line and the rate-limit sentence under Errors: Conventions says each now (#10144, step AM12 of plan api-clean-modern). The console's side card keeps the first `curl` call and links to Conventions.
