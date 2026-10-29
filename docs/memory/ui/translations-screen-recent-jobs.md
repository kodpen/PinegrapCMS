---
name: translations-screen-recent-jobs
description: Translations screen (translations.php), recent jobs list: how a job's scope is labelled, where its error shows, and which messages survive the reload after a job or a bulk action
---

## What does a recent job say it covered?
Each row of the list on the right is "<engine> · <scope>". The scope comes from `translation_jobs.scope`: `all` → "All pages"; `group:<key>` → the group's label from `pg_tr_owner_groups()` ("Products and product groups", "Forms", "Cookie window"); `page:<id>` → the page's name as the page list shows it (`page_name`). A page no longer among the translatable pages (deleted, in the recycle bin) and an unknown scope show "One page".

## Why?
Before #851 (PR #1028) every scope other than `all` read "One page", so a catalog, forms or cookie-window run looked like a single page's.

## Where does a job's error show?
As visible text in a line under the job's row (`fg-danger small`, the icon beside it `aria-hidden`). Before PR #3087 it was only a `title` on an `aria-hidden` icon: hover-only, out of reach of the keyboard and screen readers.

## What survives the reload after a job or a bulk action?
Each of them toasts and reloads the screen 0.8–1.2 s later. `afterReload(message, kind)` in `translations.js` stores the message in `sessionStorage` (`pg-tr-toast`) first, and the next load shows it once with `toast()`. `finish(done, failed, error)` stores the job's error, else the "Finished: …" counts, as an error when a text failed and as success otherwise. Nothing pending, pending by hand, Review All, Clear and Accept source store their own message. The cancel paths and the engine switch reload with no message. Before PR #3087 these messages were gone before they could be read (`tests/translations_reload_message_test.php`).
