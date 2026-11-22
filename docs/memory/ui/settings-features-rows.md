---
name: settings-features-rows
description: Site Settings › Features below the Modules board: the folded rows Site search, Sharing buttons, Speed, Badge and the rest, their card ids, state lines and what moved into them
module: features_fold
---

## Which folded rows are below the Modules board?
- Folded rows (`pg_settings_fold()`, `settings-fold-rows.md`), one open, Site search first: Site search, Sharing buttons, Visitor notifications, Maps, Clicks and scrolling, Content approval, Speed, Images, Page revisions, Signature time stamp, Badge. Order, faces, state lines: `includes/fn/features_fold.php`. No "Other settings" card is left.
- Each row holds its card under its old id; the registry's section is named as the row: `pgset-search-tuning` (Site Search Type as `pg-seg`, Synonyms, Promoted Results), `pgset-sharing` (new: Setup as `pg-seg`, Simple shows the eight services as checkboxes in two columns, Advanced the code; no popover).
- Also `pgset-page-cache` (Speed: Page Cache and Page Lifetime, Prefetch Menu Links, Performance Monitoring), `pgset-images`, `pgset-revisions`, `pgset-signature`, `pgset-badge` (new: Default Badge Label).
- Speed's line says the page cache is off while Mobile view is on; off, Maps keeps its fields and its line says so. A row's own switches are `pg-switch` left of their label; units (%, pixels, days, seconds) are suffixes after the number in every language.
- Tests: `tests/settings_features_modules_test.php`, `tests/settings_features_fold_test.php`.

Related: docs/memory/ui/settings-features.md, docs/memory/ui/settings-fold-rows.md
