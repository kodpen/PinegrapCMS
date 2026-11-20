---
name: more-features-shared-files
description: The existing files that steps of more than one of the eight plans of plans/more-features.md touch, each with its steps and their order; two steps on the same file are never open together
---

## What is the rule?
- As in the first set (architect, 2026-11-16, agent channel message 5763): the existing files several plans touch stand in one place with their steps, and two steps that touch the same file are not open at the same time. A coordinator gives the second out when the first has landed.
- A file that also stands in `plans/website-features-shared-files.md` counts both lists, and any other plan's open PR on it goes first.
- A file only one step touches is named in that step alone and is not listed here.
- Architect (2026-11-16, agent channel message 6801): a PR that adds only its module's one `require_once` line to `functions.php` does not hold that file, if the line is not put next to another open PR's line.

## Which files do several steps touch?
- `includes/migrations/2026.11.0.php` (and `install/index.php` `get_tables()` for a table): SL1 (a column), RF1 (a value), WS2 (a column), TK1 (a table and a column), TK4 (two columns and an index), CO1 (three tables). Step labels are taken from the open version's call list (`release/version-numbering.md`).
- `functions.php` (a module's `require_once`): SL1, MP2, CV1, RF1, TK1, CO1.
- `includes/fn/ecommerce.php` (the cart module): WS1 (where offers are judged), RF1 and TK1 (a call where an order is cancelled and restored), TK4 (places given back). Architect (2026-11-17, agent channel message 7603): the order is WS1, WS2, RF1, TK1, TK4; RF1 puts its call in after WS2 has landed.
- `includes/fn/order_completion.php`: RF1 (at the commission's call), TK1 (beside the gift cards' call and the folder access take-back). Never open side by side.
- `includes/fn/widgets_account.php` (`_pg_my_account_extras()`): RF3, TK2.
- `assets/js/style_designer.src.js`: MP3 and CO4 (a row in `_SD_PAGE_SCRIPTS`, CO4 also the palette rows), RF3 and TK2 (the account lists), WS5 (the token list).
- `get_page_content.php` (a script for a mark in the markup): MP3, CO4.
- `includes/settings/features.php` and `features.save.php` (a Features card): MP4, TK1, CO2.
- `includes/fn/output.php` (a menu link): CV2, RF4, CO2.
- The API schema (`includes/api/schema.php`): CV3, WS6, TK5.
- `includes/api/outbound/webhooks.php` (a new event's name; `includes/workspace/watch.php` where flows offer it): RF4, TK5, CO5.

## Which files does one step touch, yet other plans hold?
- `assets/js/workspace_flow.js` and `assets/js/workspace.js`: DG3. `job.php`: CV1. `submit_order.php`: WS4. `edit_offer_f.php`: WS1. `includes/fn/consent.php`: MP4. `get_page.php`: CO3.
- `edit_submitted_form.php` and `view_submitted_forms.php`: MP2. `reserve_calendar_event.php`, `edit_calendar_event.php`, `get_calendar_event_view_screen_content.php` and the event view widget in `includes/fn/widgets.php`: TK4 (the last three found in TK4, PR #10578: the waiting line is drawn by both event pages, and raised spots mail those waiting). `includes/fn/stock_notify.php`: TK4, and VP5 of `plans/visitor-push.md`. `includes/fn/privacy.php`: CO1.
- Each step checks the open PRs' files before it opens.

## How is the list kept?
- A step that finds it must touch a file of this list which its plan did not name writes it here in its own PR and says so in the PR body.
- Deleted with `more-features.md`.
