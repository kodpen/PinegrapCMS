---
name: settings-modal-navigation
description: Settings modal vs rest of panel: no settings breadcrumbs/back button, pgSettingsGo, preloader curtain, nested modals, return URL, notice handoff.
---

## Why do screens have no breadcrumb or back button pointing at settings?
- A breadcrumb is a location claim ("you are here, above is X, click to leave"). Settings is a box opened over the screen, so such a crumb goes nowhere: it opens a dialog over the current page and reads like a broken link.
- Removed from `settings_<cat>.php`, site log, sessions, app access, marketplaces, configuration and find-replace screens. Only the real parent remains (sessions narrowed to one account: "Tüm Oturumlar"). On those screens the `.pg-breadcrumb` strip is not printed at all; the only door to settings is the header gear, present on every screen.
- The back button is gone too: an arrow looks like "up" but there is no up; pressing it opens a dialog over the page you are already on.

## How does a non-link control navigate without being swallowed?
- Call `pgSettingsGo(url)`. The header back button is a `<button>` with `onclick`; the delegated listener never sees it. `pg_page_shell`'s `cancel['url']` therefore goes through it; a screen writing `window.location` by hand via `cancel['onclick']` bypasses the dialog.
- `pg_preloader_markup()`'s click listener runs on the document in the CAPTURE phase, before our delegated one; without asking, it would drop the full-screen curtain over a page that goes nowhere. It keeps no list of its own and asks `pgSettingsModal.asked(a)`.
- A Bootstrap modal does its own link handoff: the command palette's settings chip calls `stopPropagation()` and opens the dialog in the search box's `hidden.bs.modal`. Two modals open at once leave a second backdrop and a body that cannot restore its scrollbar.

## How does a tool that ends on another screen return to settings?
- Use `pg_settings_return_url()`. There is no settings screen to return to; the address becomes `welcome.php#settings/<pane>/<card>` and the dialog opens itself on arrival. Users: `purge_cache`, `clean_up`, `cloudflare`, `software_update`, `view_parasut_inbox`.

## How do sentences left by such a tool reach the dialog?
- Those tools write `add_notice()` / `mark_error()` on `liveform('settings')` and redirect; no screen is left to read them. The endpoint takes them over in the GET branch with `get_notices()` + `get_errors()`, drops the form with `remove_form()`, and the dialog shows them on the first pane.
- Exactly once: removing before takeover destroys the sentence; taking over without removing brings it back on every open.

Related: docs/memory/ui/settings-modal-entry-points.md
