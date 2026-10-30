---
name: screen-header-actions
description: Plan (owner 2026-10-30): no lone buttons or texts under a screen's title; back only by arrow and breadcrumb, the rest in one toolbar; steps in screen-header-actions-steps.md
---

## What is the goal?
- Owner, 2026-10-30, with a screenshot of Calendar subscription: some screens have buttons, links and short texts sitting alone at the left or the right under the title. That screen's "Work Calendar" button comes from `pg_page_shell()`'s `actions` (`includes/fn/output.php` 502-554). It is a full-size button in `.pg-page-actions`, which has no CSS, outside `#content`'s padding. `workspace_calendar_feed.php` is its only user.
- Inventory (2026-10-30, a29a87c68):
  - About 60 screens wrap `#button_bar` in `.row > .col-12 … md:text-start`; `page-button-bar.md` forbids this. Seven of them open a second tool column beside it.
  - Lone bars hold one button: `erp_dashboard`, `add_return`, `workspace_mcp`, and the "Duplicate" rows of eight edit screens.
  - Some bars start with the grow spacer, so their one control sits alone at the right: `erp_invoice_recurrences`, `view_audit_log`, `view_not_found_log`.
  - Fact lines and second headings sit above the bar: "Disk Usage", `[name]`, "Page Style Body Tag".
  - "Back to the parent" is drawn nine ways.

## What is decided? (owner, 2026-10-30)
- The way back to a parent or list screen is only the back arrow and the breadcrumb strip under the top bar. Copies in toolbars, cards and content go: "All Orders", "All Returns", "All Products", "← Marketplaces", "Work Calendar", "All tables", "MCP servers", and the bottom link on Work Calendar.

## Which rules does a screen follow?
- One toolbar directly under the title, in the slot order of `ui/admin-screen-toolbar.md`. Filters, sort and view controls go at its right end, never in a second column. More than four buttons go into a menu (`view_pages` shows eight today).
- A related-screen link that is not the parent is a slot 2 `btn-sm btn-text theme-secondary`. `admin-screen-toolbar-rules.md` says `btn-outline-secondary`; it is aligned in H5.
- No lone row: a single action goes into the toolbar; a fact ("Disk Usage") goes into a card or the toolbar's grow slot. No second heading that repeats the title.
- A screen-wide action is never in a card header (for example "Clear Log" on `view_waf_log`, "New department" on `workspace_settings`).

## What is still open?
- Phones: does the toolbar stay at the top while the list scrolls? Today it does not stick. Ask before H3.

## Where to start?
- `pg_page_shell()` / `output_header()` (`cancel`, `breadcrumb`) and `pg_send_to_url()` in `includes/fn/core.php`.
- CSS: the "Page action bar" and "Admin screen toolbar" blocks of `backend.src.css`, then `php tools/build_css.php`.
- Tests: `tests/back_arrow_targets_test.php`, `back_arrow_send_to_test.php` and `panel_screen_titles_test.php`, which reads every `pg_page_shell()` call.
