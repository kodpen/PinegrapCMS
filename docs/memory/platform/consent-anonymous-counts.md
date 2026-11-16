---
name: consent-anonymous-counts
description: The one cookie notice check of the anonymous counts (clicks and scroll depth, a pop-up's events): counted unless statistics were refused; why it is not pg_consent_allows()
---

## What is the rule?
- Architect (2026-11-16, agent channel message 6043), carrying out the owner's poll 75 (`workflow/decisions-made-product-60.md`): a total that knows nobody waits for no consent.
- Cookie notice off: counted. Notice on: not counted when `pg_consent` refuses statistics (`a0`, which closing the notice writes too); counted with `a1`, or when statistics is not named yet (not answered). A cookie that cannot be read counts as not answered.

## Which function answers it?
- Server: `pg_consent_statistics_not_refused()` in `includes/fn/consent.php`, over `pg_consent_choices()`; its pure core `pg_consent_statistics_not_refused_by($enabled, $choices)`. Added by #9902.
- Callers: `pg_page_clicks_allowed()` (`visitors/page-clicks.md`) and `pg_auto_dialog_count_allowed()` (`visitors/auto-dialog-counts.md`). A new anonymous count asks this one; nobody writes a second check.
- Browser: `software.statistics_allowed()` in `frontend.src.js` still passes only `a1`; CM2 of `plans/click-scroll-map.md` moves it to this rule and a name that says the same, tested on the same cases (notice off, `a1`, `a0`, not answered, unreadable).

## Why not pg_consent_allows()?
- `pg_consent_allows()` guards cookies and held scripts, where the rule is a yes; it stays as it is. A check that lets an unanswered visitor through is named for what it answers ("not refused"), so nobody sets a cookie with it.

## Tested?
- `tests/page_clicks_test.php` (`test_consent_statistics_not_refused_is_the_anonymous_counts_check`).
