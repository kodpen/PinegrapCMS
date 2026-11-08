---
name: pages-list-bulk-edit-fixes
description: Pages list and Mass Edit fixes (#5288-#5293): a deleted page in Delete Selected, too long values, Find & Replace skips, stale SEO scores, column names and row labels
---

## What does Delete Selected do with a page deleted meanwhile?
- `edit_pages.php` skips a selected id whose row is gone (another tab, another manager, an old list) and adds "One of the selected pages no longer exists, so it was skipped." (or the `{var:1}` form). Before, it read a null row: six PHP warnings, a nameless "Page not found." from `pg_recycle_page(null)`, and without the Recycle Bin a DELETE on an empty id counted as deleted (#5288).

## What does the Mass Edit direct save do with a value longer than its column?
- Page name (100, `pg_name_too_long()`), Web Browser Title and Description (255, `$too_long_for_column`) are not saved; the page is named once in "Some values were not saved because they are longer than the field allows: …". The `maxlength` on the boxes is only in the browser; before, the non-strict session cut the value and the screen said "updated" (#5292). Find & Replace already skipped such values (`name-length-refusal.md`).
- Find & Replace with every match skipped says "No change was applied." and the skip reasons, not "No matching text found." (#5291).
- The card heading is a flex row and its name span has `min-width: 0`, so `text-truncate` cuts a long name: the panel's `.card-header` is `display: block`, where an inline span cannot truncate and widened the screen (#5289).

## Which pages does "SEO Score Not Calculated" list?
- `view_pages.php?filter=seo_not_calculated`: `seo_checked_at = 0` or `seo_analysis_current = 0` (never scored, or marked stale by a change), as its subheading says. `pg_seo_pages_summary()` counts the same rows; the SEO strip reads "{var:1} not calculated or out of date" (#5290).

## What do the Pages list's column names and row labels say?
- The device toggle in the style column heading is an `<a>` with `aria-label`, its ligature in an `aria-hidden` `material-icons` span.
- Turkish "Select {var:1}" is `{var:1} seç` without `|c`: a page name is an address and is not capitalised (#5293).

Tests: `tests/seo_pages_summary_test.php` (`counts_stale_scores`). The screens were not run in a browser.
