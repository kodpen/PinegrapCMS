---
name: bug-hunt-areas-cookie-consent
description: The bug hunt of the cookie notice (area 46, 2026-11-24): the four faults fixed, what was read clean, what is no fault, the embedded-video question left for the architect
---

## What was hunted?
- Area 46 "cookie-consent": `includes/fn/consent.php`, `assets/js/pg_consent.js`, `consent_answer.php`, the Google Analytics hold in `get_page_content.php`, the page cache's consent bypass (`includes/fn/page_cache.php`), the `pg:consent` listeners (`frontend.src.js` pop-ups, `assets/js/pg_map.js`), every front-end `setcookie()`.
- Asked: nothing optional before a yes, a no remembered, a changed choice at once, the consent record, language and keyboard, cached pages.

## What did it fix?
- PR #11461 "Bug hunt — cookie consent", four faults, each with its test:
- Closing the notice on a page that asks about a new category refused every category, deleted the statistics cookies and reloaded. Now it refuses only the new one (`platform/cookie-consent-new-question.md`; `tests/consent_js_revoke_test.php`).
- While that question waited, the scripts held for a category already allowed did not start. They start now, with no `pg:consent` event (same file and test).
- A site tag marked `data-pg-consent` without `type="text/plain"` ran before any answer while the notice listed it as waiting. `pg_consent_hold_marked()` holds it; unquoted categories are listed (`platform/cookie-consent-new-cookies.md`; `tests/consent_test.php`).
- A refusal over the answer endpoint's 20-an-hour address limit skipped the refusal hooks, so an order's ad rows kept the buyer's ids. `pg_consent_answer_acts()` runs them for 429 too (`core/consent-log-cookie-notice.md`; `tests/consent_test.php`).

## Which issues did it open?
- None.

## What was read clean?
- Cached pages: a request with `pg_consent` neither fills nor is served from the page cache, and gets no public `Cache-Control`. The cached copy carries the no-answer default, and `pg_consent.js` reads the cookie on load. A CDN in front serves it the same way.
- Consent Mode default at the top of `<head>`, update before held scripts, GA held until statistics are allowed, every optional server cookie through `pg_consent_setcookie()`, revoke names, the answer endpoint's origin, size and value checks.
- Keyboard: focus on Settings when opened by a control, into the settings view, Escape back to the first view, visible focus rings. Language: `lang()` keys of `pg_consent_ui_keys()`.

## What is no fault?
- A visitor who answered on a page without optional categories ("OK", value `1`) is asked again on a page that has one: the page's categories decide.
- The close button keeps its label "Close and keep only the necessary cookies" on a page that asks a new category. The label is drawn into the cached page, which knows no visitor.

## What is left for the architect?
- An iframe from the rich-text editor's Media Embed (YouTube, Vimeo) or a site's own HTML loads before any answer: the notice holds scripts and map tiles, not iframes. Holding them needs a product decision: which category, the placeholder a visitor sees, and whether known hosts are held automatically. Asked in the PR body.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/platform/cookie-consent-notice.md
