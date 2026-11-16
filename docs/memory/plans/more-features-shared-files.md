---
name: more-features-shared-files
description: The existing files that steps of more than one of the eight plans of plans/more-features.md touch, each with its steps; two steps on the same file are never open together
---

## What is the rule?
- As in the first set (architect, 2026-11-16, agent channel message 5763): the existing files several plans touch stand in one place with their steps, and two steps that touch the same file are not open at the same time. A coordinator gives the second out when the first has landed.
- A file that also stands in `plans/website-features-shared-files.md` counts both lists, and any other plan's open PR on it.
- A file only one step touches is named in that step alone and is not listed here.

## Which files do several steps touch?
- `includes/migrations/2026.11.0.php` and `install/index.php` (`get_tables()`): DG1, SL1, MP2, CV1, RF1, WS2, WS6, TK1, TK4, CO1. Step labels are taken from the open version's call list (`release/version-numbering.md`).
- `functions.php` (a module's `require_once`): SL1, MP2, CV1, RF1, WS2, TK1, TK4, CO1.
- `job.php` (a sweep or a pass of the general job): DG1, MP2, CV1, TK4.
- `includes/fn/ecommerce.php` (the cart module): RF1 and TK1 (a call where an order is cancelled and restored), WS1 (where offers are judged), WS2 (the price resolver), WS6 (the buyer's account), TK4 (`check_reservations()`, places given back). Other plans' open PRs hold it often: a coordinator looks at the open PRs' files first.
- `includes/fn/order_completion.php`: RF1 (at the commission's call), TK1 (beside the gift cards' call and the folder access take-back).
- `submit_order.php`: WS4 (the minimum order), WS6 (the buyer's account).
- `includes/fn/widgets_account.php` (`_pg_my_account_extras()`): RF3, WS6, TK2.
- `assets/js/style_designer.src.js`: MP3 and CO4 (a row in `_SD_PAGE_SCRIPTS`, CO4 also the palette rows), RF3 and TK2 (the account lists), WS5 (the token list).
- `get_page_content.php` (a script for a mark in the markup): MP3, CO4.
- `includes/settings/features.php` and `features.save.php` (a Features card): MP4, WS2, TK1, CO2.
- `includes/fn/output.php` (a menu link): CV2, RF4, WS3, CO2.
- `includes/fn/contacts.php` and `includes/fn/privacy.php` (who holds a contact, the export and erasure lists): CV1, RF1, WS6, TK4; CO1 the second file only.
- The API schema (`includes/api/schema.php`): CV3, WS7, TK5.
- `includes/api/outbound/webhooks.php` (a new event's name; `includes/workspace/watch.php` where flows offer it): RF4, TK5, CO5.

## Which files does one step touch, yet other plans hold?
- `assets/js/workspace_flow.js` and `assets/js/workspace.js`: DG3. `includes/fn/consent.php`: MP4. `get_page.php`: CO3. `reserve_calendar_event.php`: TK4. `edit_offer_f.php`: WS1. Each step checks the open PRs' files before it opens.

## How is the list kept?
- A step that finds it must touch a file of this list which its plan did not name writes it here in its own PR and says so in the PR body.
- Deleted with `more-features.md`.
