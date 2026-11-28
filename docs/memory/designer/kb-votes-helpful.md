---
name: kb-votes-helpful
description: "Was this helpful?" on a collection record's page (help centre HC8): the kb_votes table, the Yes and No button actions, kb_vote.php, the cookie, the two counts on the record list
module: kb_votes
---

## What is stored?
- Step 11.0.289 (`upgrade_2026_11_0_kb_votes()`, owner's yes in `workflow/decisions-made-schema-54.md`): table `kb_votes` (id, form_id, record_id, answer TINYINT 1 Yes / 0 No, created_at), keys on (record_id, answer) and form_id; listed in `get_tables()`. No address, IP or user.
- Code in `includes/fn/kb_votes.php`. Until the step ran (`pg_kb_votes_ready()`), the buttons stay hidden, no script is added and the list shows no counts.

## How does a page ask?
- In a Form Item View widget, a button (or a link, made a button) bound to the action "Was this helpful? Yes" (`kb_vote_yes`) or "… No" (`kb_vote_no`), offered in the designer's action list for `form_item_view`. The question text is the designer's own.
- `_pg_sw_apply_kb_vote_bindings($tree, $open)` draws both hidden (`hidden` + `d-none`); `$open` only for a collection form (`custom_form_pages.collection = 1`, `pg_kb_vote_form_open()`) and not in an e-mail; then they carry `data-pg-kb-vote` (yes/no) and `data-pg-kb-record="^^submitted_form_id^^"`.
- `pg_kb_vote_inject()` (from `get_page_content.php`) adds `assets/js/pg_kb_vote.js` with its labels only to a page holding such a button. The script shows the buttons when a record is shown and the cookie does not list it; otherwise, and after a vote, it hides them and writes "Thank you for your feedback." after the last one (`[data-pg-kb-thanks]`, `role="status"`). Read from the cookie in the browser, so a cached page asks once too.

## What does kb_vote.php accept?
- Root, public, POST only (405): `record` (forms.id), `answer` yes|no and the header `X-PG-Vote` (400 without them; a plain cross-site form cannot set it). No form token.
- 409 `voted` when the cookie lists the record; 429 past 30 votes in 10 minutes per `waf_ip_subject()` (scope `kb_vote`, logged `rate-kb-vote`); 404 unless the record is complete and its form a collection (`pg_kb_vote_record_form()`, the form id is read from the record, never posted). Then one row and the cookie.
- The record's own view access (public, submitter only, signed in) is not checked: the endpoint does not know the page.

## Which cookie?
- `pg_kb_votes` (`PG_KB_VOTE_COOKIE`): record ids joined by `.`, newest last, at most 100, a year, `SameSite=Lax`, not HttpOnly (the script reads it; it holds only ids). Listed as necessary in `pg_consent_catalogue()` while the table is there.

## Where are the counts shown?
- The record list of submitted forms (`view_submitted_forms.php`) narrowed to one collection form (Custom Form, or one form in the advanced filters: `pg_kb_vote_list_form()`): columns "Helpful: Yes" and "Helpful: No" before Action, number kind, not sorted, empty for none (`pg_submitted_form_list_vote_columns()` / `_vote_cells()`, one grouped read per batch, `pg_kb_vote_counts()`).

## Tests?
- `tests/kb_votes_test.php`. The step, the endpoint, the list and the page against a database and in a browser were not run in the cloud session (PR #12230).

Related: docs/memory/designer/help-centre-collections.md, docs/memory/designer/search-results-folder-and-article-suggest.md
