---
name: application-access-new-sheet
description: Application Access's New application sheet: a drawer in three steps, the starting point cards of api_app_templates(), what each ticks, the sign-in card when OAuth is set up, the create branch's checks
---

## What does "New application" open?
- A drawer `#api_new` (`min(760px, 100%)`) drawn by `api_app_new_view()` (`includes/api/app_new_view.php`), with a step line (Bootstrap `.stepper-horizontal`, `ui/step-rail.md`): 1 What connects, 2 Details and permissions, 3 Connect. Since #10745.
- Step 1: one choice card per starting point (`name="start"`), the first picked. Next goes to step 2; Back and Change return to step 1 and keep what was typed. Nothing is written before Create.
- Step 2: the picked card's icon, title and "Acts as" line, Name, Description, the automation note, the permission rows, and a closed "Advanced" with Allowed addresses and Requests per minute.
- Step 3 is still the one-time sheet the page shows after Create (secret, team devices or OAuth sheet); it joins the guides with AA3 of the redraw plan.

## Which starting points are there and what do they tick?
- `api_app_templates($groups, $contract_scopes, $money_scopes, $oauth_app)` (`includes/api/app_templates.php`, pure but for `lang()`), in order:
  - AI assistant with sign-in: kind `oauth`, nothing ticked, no secret.
  - Automation platform: kind `server`, `api_app_start_choices()` of the automation contract (`application-access-automation.md`).
  - Own integration: kind `server`, nothing ticked.
  - Read-only reporting: kind `server`, the read of every row that has one, no write.
  - Mobile app and team devices: kind `device`, nothing ticked, no secret.
- No card ticks a row whose scope, or the read its write holds, has a `moves_money` route: `api_app_money_scopes(api_schema())` and `api_app_template_safe_choices()` drop it (tests/api_app_templates_test.php).
- The "Agent in a channel" card comes with step AA5 of the redraw plan.

## What does the sign-in card do once OAuth is set up?
- The site serves one OAuth application (`pg_oauth_app()`). When it exists the card shows "Set up already" and Next reads "Open it": the sheet closes and that application's drawer opens. No second OAuth application is made.

## What does the create branch check?
- `start` is read with `api_app_template_posted()` against the cards and decides the kind; a posted `kind` counts only without a valid `start`.
- Name, Description and Allowed addresses go through `api_app_text_too_long()`, the addresses through `api_app_ip_allowlist_bad()`, the rate through `api_app_rate_limit_in()`, as the drawer's Security tab; a refusal writes nothing. Addresses and rate are written right after `api_app_create()`.
