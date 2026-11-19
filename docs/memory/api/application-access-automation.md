---
name: application-access-automation
description: Application Access for automation platforms: the "Automation platform" starting point, the secret sheet's three values, New subscription and Send a sample, one create check with POST /webhooks
---

## What does the "Automation platform" starting point do?
- New application › "Starting point", shown only for "An integration": "I will choose the permissions" (nothing ticked, as before) or "Automation platform", which ticks the rows of `api_app_start_choices(pg_automation_contract_scopes(), api_scope_groups())` (`includes/api/app_access.php`) and names them in one line.
- A row offering both levels of a contract scope takes write; a scope no row offers (a module that is off) is left out. Tested: never a scope holding a `moves_money` route.
- Nothing about the choice is stored: the application is an integration like any other. The posted `start` only adds the note to the one secret sheet that follows (`api_new_credentials['automation']`).

## What does the secret sheet show?
- Every integration's sheet (made or rotated): 1 Site address (the API found from it through `/.well-known/pinegrap.json`), 2 Key, 3 Secret, each with Copy; the note "An automation platform asks for these three values…" only after the automation starting point.

## How does "New subscription" make a subscription?
- Events tab › New subscription: the address (textarea, 500 characters), the events as a searchable list, each with its area pill (`api_app_event_area()`: the name's first segment, order.* Orders; a module's event its module) and catalogue line (`lang()`), chosen ones as chips posting `hook_events[]`, at most 20; the count "n / 50" beside the tab's line. Posts `api_action=webhook_create` through the drawer form.
- One check for both callers: `api_webhook_create_check($url, $events, $held)` (`includes/api/resources/webhooks.php`) answers the route's refusals (status, code, field, text): url required and within 500, events within 20, `api_http_check_url()`, every name in the catalogue, the limit (`api_webhook_held_count()`). Then `api_webhook_store()` writes the row. `api_webhook_create_bounds()` equals the route row's `max_length`/`max_items` (tested).
- The channel group stays API-only: the panel makes whole-site subscriptions.
- Nothing typed is dropped: while the form holds an address or a chosen event, the drawer's Save does not post; it shows the form with "The subscription is not created yet…" (architect 2026-11-17; `hookFormHolds()`, tested).
- The new parts use the design language: `pg-button` (Create the form's one fill, the rest quiet or ghost), `pg-icon-button`, `pg-input`, `pg-field-label`, `pg-checkbox`, `pg-pill`. The starting point copies the dialog's own choice cards (no radio recipe yet); the old rows' Stop/Remove and the tab strip wait for panel-old-screens.
- After the post the drawer opens on the Events tab again (`$_SESSION['software']['api_hooks_back']`): the form with its error and values, or the signing secret once, removed on leaving the tab.

## How is a sample sent?
- "Send a sample" on each subscription that is not stopped: a select of its own events, Send, Cancel. `api_action=webhook_sample` uses the route's `api_webhook_test_refusal()` and `api_webhook_test_queue()`: queued for that subscription only, never announced, `X-Pinegrap-Test: 1`. The line ("The sample of … was queued") shows under that subscription.

## Where is an address shown whole?
- Only in the form while typed; the list, the Event notifications card and the notices show scheme and host (`pg_ops_alert_mask_url()`).

## Which names may the screen write?
- No platform name until our apps are out (owner, poll 121; `workflow/decisions-made-product-70.md`); tested. The Events tab's guide "Automation platforms" names the two ways.

## What does a locked row show?
- None of New subscription, its form or Send a sample (`#api_drawer.api-locked`); the POST is refused before any branch.

Related: docs/memory/api/automation-contract.md, docs/memory/api/api-webhook-subscription-limit.md
