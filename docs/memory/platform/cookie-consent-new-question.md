---
name: cookie-consent-new-question
description: A page that asks about a category the visitor has not answered yet: allowed scripts start while it waits, closing refuses only the new category (PR #11461)
---

## What happens when a page asks about a category the visitor has not answered?
- The notice opens (a map block brings marketing to a visitor who answered statistics only). Meanwhile the scripts held for an allowed category start and the stored answer is sent as an update, but no `pg:consent` event goes out, so pop-ups still wait for the answer (`start(choices, false)` in `pg_consent.js`).
- Closing it refuses only the new category and keeps every answer already given, with no reload. Reject all still refuses everything. Before PR #11461 closing refused all, deleted the statistics cookies and reloaded the page; the allowed scripts waited for the answer.

## Where is it tested?
- `tests/consent_js_revoke_test.php` runs `pg_consent.js` in Node: `test_consent_js_closing_a_new_question_keeps_the_earlier_answers`, `test_consent_js_allowed_scripts_start_while_a_new_question_waits`.

Related: docs/memory/platform/cookie-consent-notice.md
