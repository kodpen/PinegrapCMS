---
name: translations-screen-recent-jobs
description: Translations screen (translations.php), recent jobs list: how a job's scope (all, group:catalog / forms / ui, page:ID) is labelled
---

## What does a recent job say it covered?
Each row of the list on the right is "<engine> · <scope>". The scope comes from `translation_jobs.scope`: `all` → "All pages"; `group:<key>` → the group's label from `pg_tr_owner_groups()` ("Products and product groups", "Forms", "Cookie window"); `page:<id>` → the page's name as the page list shows it (`page_name`). A page no longer among the translatable pages (deleted, in the recycle bin) and an unknown scope show "One page".

## Why?
Before #851 (PR #1028) every scope other than `all` read "One page", so a catalog, forms or cookie-window run looked like a single page's.
