---
name: designer-publish-check-results
description: The Publish check in the Publish sheet (PC4): pages checked, a row's result, the five groups, a path that selects the element, a blocking page, its part
---

## Where does it run?
- The part `publishcheck` (`assets/js/style_designer_publishcheck.js`, bundle with `pg_publish_findings.js` and `pg_publish_content.js`), handed with the Publish sheet (full access, drafts ready, not a component alone). `_pgPublishSheet()` gives the sheet `bridge.check(keys, done)` and `bridge.findings(key, retry)`. No bar button; nothing runs while editing.
- Built in #11577 (PC4 of `plans/designer-publish-check.md`).

## Which pages are checked, and when?
- The ticked pages when the sheet opens, a page when it is ticked, all with "Select all". An unticked page has no result.
- Per page: `validateTree()` of its tree, one `prototype_map` read of its links (`PGProtoHotspots`), `seo_check` with `_sdSeoCheckBody(p, t)` (the audit panel's body, any tab) for a saved page only, and `PgPublishContent` with the starters' samples (components' `defaultProps` and every block family's built tree, built once).
- The answer is kept per page until its tree, name, title, description, keywords or search settings change (`_pcSig()`); reopening the sheet gives it at once.

## What does a row show?
- `PgDesignerPublish.result()`: "Checking…" with a spinner, then a button "Ready", "{n} to look at" or "{n} blocking" (a page with both says blocking), or "Not checked" when the part could not run. The foot says "Checking {n} pages…"; Publish selected stays off while a ticked page is checked (`plan().waiting`).
- The button opens the findings under the row; one row open at a time.

## How are the findings shown?
- Five groups always listed in `PgPublishFindings.groups()` order, each with its count or "Nothing found". A failed check: "Not checked", "This check could not run. It blocks nothing." and "Try again" (forgets the kept answer, checks again).
- A page not saved yet: Accessibility and Search engines say "The rest of this check runs once the page is saved."
- A card: amber triangle, or a stop sign with a rose line when blocking; the sentence; the detail (not for audit rows); the path from the page's top ("Main › Hero › Button", a shared component's name first). No node: "Page settings", not a link. PC5 adds Fix, Show me and Ignore.

## What does a press on a path do?
- The sheet closes (`PgDesignerPublish.close()`), `_sdSelectFound(key, node, shared)` opens the page's tab and selects the element; one line at the bottom over the canvas repeats the sentence with "Back to Publish", which reopens the sheet with that row open (`bridge.reopen`). The line goes with Escape or the next press anywhere else.

## How is a blocking page published?
- Its box is off and empty, the name dimmed; "Publish anyway" ticks it and asks "Why is it published with a blocking finding? The reason goes to the Site Log." (500 characters). Left out, a changed page on the site is held out of the save, a draft stays a draft.
- `plan()` gives the reasons as `checks` (apart from the approval `reasons`); the editor sends `publish_check_reason` with the page; the save logs "page (…) was published with a blocking Publish check finding: …" when the page ends on the site (`pg_designer_publish_check_reason()`). Anyone who may publish may do it; the server checks nothing more.

## What is not wired yet?
- Weight: its group says "Not measured"; `seo_check` does not measure yet (PC5: the draft render's files need their disk paths for `pg_publish_weight_local_files()`).
- Component field leftovers (`leftovers`) and `unreached` are not asked: no editor source gives them.

## What does it cost?
- At opening: core +0.9 KB, `pg_designer_publish.js` +8.4 KB (+2 KB gzip); the part, 19 KB (7 KB gzip), at the first check.

Tests: `tests/designer_publish_sheet_test.php`, `tests/designer_publish_check_test.php`.
