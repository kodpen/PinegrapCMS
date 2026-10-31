---
name: settings-modal-navigation
description: Settings modal vs rest of panel: no settings breadcrumbs/back button, pgSettingsGo, preloader curtain, one dialog after another, return URL, notice handoff.
---

## Why do screens have no breadcrumb or back button pointing at settings?
- A breadcrumb is a location claim ("you are here, above is X, click to leave"). Settings is a box opened over the screen, so such a crumb goes nowhere: it opens a dialog over the current page and reads like a broken link.
- Removed from `settings_<cat>.php`, site log, sessions, app access, marketplaces, configuration and find-replace screens. Only the real parent remains (sessions narrowed to one account: "Tüm Oturumlar"). On those screens the `.pg-breadcrumb` strip is not printed at all; the only door to settings is the header gear, present on every screen.
- The back button is gone too: an arrow looks like "up" but there is no up; pressing it opens a dialog over the page you are already on.
- A settings list keeps no arrow either: All Referral Sources lost its arrow to the Dashboard, like All Currencies (`back-arrow-targets.md`).

## How does a non-link control navigate without being swallowed?
- Call `pgSettingsGo(url)`. The header back button is a `<button>` with `onclick`; the delegated listener never sees it. `pg_page_shell`'s `cancel['url']` therefore goes through it; a screen writing `window.location` by hand via `cancel['onclick']` bypasses the dialog.
- `pg_preloader_markup()`'s click listener runs on the document in the CAPTURE phase, before our delegated one; without asking, it would drop the full-screen curtain over a page that goes nowhere. It keeps no list of its own and asks `pgSettingsModal.asked(a)`.
- A dialog does its own link handoff: the command palette's settings chip calls `stopPropagation()`, hides the search box and opens the settings in its `hidden` handler (`pgUI.dialog.on($modal, 'hidden', …, {once: true})`), so one dialog gives way to the other instead of opening while the first is still closing.

## How does a tool that ends on another screen return to settings?
- Use `pg_settings_return_url()`. There is no settings screen to return to; the address becomes `welcome.php#settings/<pane>/<card>` and the dialog opens itself on arrival. Users: `purge_cache`, `clean_up`, `cloudflare`, `software_update`, `view_parasut_inbox`.

## How do sentences left by such a tool reach the dialog?
- Those tools write `add_notice()` / `mark_error()` on `liveform('settings')` and redirect; no screen is left to read them. The endpoint takes them over in the GET branch with `get_notices()` + `get_errors()`, drops the form with `remove_form()`, and the dialog shows them on the first pane.
- Exactly once: removing before takeover destroys the sentence; taking over without removing brings it back on every open.

## How does the search in the dialog work? (#4118)
- A box over the category list (`#pg_settings_modal_search`, outside the form: never posted, never makes a pane dirty). On first use it asks `settings_pane.php?search=1` once per page: `pg_settings_search_build()` draws every category with `pg_settings_fragment()` (output buffered, one failing category keeps its registry row) and reads each card's heading, labels and legends (`pg_settings_card_labels()`); `pg_settings_search_index()` keeps only the sections the registry lists for this role. No hand list: a field added to a card is found.
- Matching (`backend.src.js`, `score()`): card name, then keywords (the palette's prefix rule), then field labels, then every word of a multi-word query somewhere; Turkish letters folded. Hits replace the list; a hit calls `open(pane, card)`, the same as a `#settings/<cat>/<card>` link. Escape empties the box before it closes anything; Down/Return go to the first hit.
- Palette keywords fixed with it: "logo" no longer lands on E-Invoice (Logo İşbaşı stays findable by "isbasi"), "prefetch" finds Feature Options, "workspace" finds Related screens in Turkish too.

Related: docs/memory/ui/settings-modal-entry-points.md
