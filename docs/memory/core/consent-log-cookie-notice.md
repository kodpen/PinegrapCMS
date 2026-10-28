---
name: consent-log-cookie-notice
description: How the cookie notice's answers reach consent_log: consent_answer.php, the cookie as baseline, the same-origin check instead of a token, rate limit, signed-in and ghosting visitors
---

## Which answers are recorded?
- Every answer the visitor gives in the notice: Save, Accept all, Reject all, and closing it before answering (stored as a refusal). `pg_consent.js` `decide()` calls `report()` with the `pg_consent` value before and the one it just wrote. The stored answer that `signal()` re-sends on every page view is never posted, and nothing is posted when the value did not change.
- Channel `cookie`, source `banner`, purpose the category (`analytics`, `marketing`), status `granted` or `refused`.

## How is "changed" decided?
- `pg_consent_answer_changes($before, $answer)` (`includes/fn/consent.php`, pure): one row per category whose answer differs from the value before; a category the answer leaves out is no change; an unreadable value before counts as no answer. The baseline is the visitor's own cookie, not the log: an anonymous visitor has no rows to compare with, and each browser answers for itself (a signed-in person accepting on a second device gets rows again).
- The answer must be a value the script writes (`pg_consent_answer_valid()`: `1`, then known letters once each with 0 or 1); anything else is 400 and writes nothing.

## Who is the person on a row?
- Anonymous: none (only ip and user agent, as `pg_consent_log_record()` takes them from the request). Signed in: `USER_ID` and the person token of `USER_EMAIL_ADDRESS` (`initialize_user()`, loaded by id, so a rename does not lose it). While staff ghost a customer the row has no person: the answer is the staff member's.
- No id is kept in the cookie, so anonymous rows of one browser cannot be tied together; decided so (#2435): each row stands alone.

## How is the endpoint protected?
- `consent_answer.php` (root, URL-called): POST only (405), `pg_consent_answer_same_origin($_SERVER)` instead of a form token, because a cached page carries none (403): `Sec-Fetch-Site` must be `same-origin` when sent, otherwise the `Origin` host and port must equal `Host`; neither header is refused. Writes nothing while the setting is off or before 11.0.16.
- Rate: `pg_consent_answer_throttled()` counts per address (`waf_rate_exceeded()`, scope `consent_answer`, 20 an hour), which works with the firewall off; past it 429, nothing written, logged as `rate-consent-answer`. The firewall's own limits are not enough: it is off and in monitor mode by default, and the origin check stops pages, not scripts (a curl loop wrote two rows per request).
- Both fields are refused over 32 bytes (`PG_CONSENT_ANSWER_MAX_LENGTH`) before they are split: an 8 MB value exhausted memory in `explode()`.
- `pg_page_cache_request_dirties()` skips the script, so a signed-in answer does not clear the page cache.
- The script posts with `navigator.sendBeacon` (a form-encoded Blob, no preflight, survives the reload a withdrawal triggers), falling back to an XHR. The URL comes from the notice's `data-log`, drawn only when the page has an optional category.

## Tests?
- `tests/consent_test.php`: the strict value check, the changes, the same-origin cases, `data-log` in the markup and `report()` called from `decide()` only.

Related: docs/memory/platform/cookie-consent-notice.md, docs/memory/core/consent-log-and-audit-trail.md, docs/memory/core/consent-log-writers.md
