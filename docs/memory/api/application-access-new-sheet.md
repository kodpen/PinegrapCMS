---
name: application-access-new-sheet
description: Application Access's New application sheet: a drawer in three steps, the starting point cards of api_app_templates(), what each ticks, the sign-in card when OAuth is set up, the create branch's checks
---

## What does "New application" open?
- A drawer `#api_new` (`min(760px, 100%)`) drawn by `api_app_new_view()` (`includes/api/app_new_view.php`) to the owner's drawing `plans/app-access-redraw/app-new.html` (yes in poll 188), with a step line (Bootstrap `.stepper-horizontal`, `ui/step-rail.md`): 1 What connects, 2 Details and permissions, 3 Connect. Since #10745.
- Step 1: one choice card per starting point (`name="start"`), the first picked: a `pg-icon-tile` in the card's tone, title, a "Set up already" pill or nothing, what it is for, "Acts as: <b>…</b>" and "Key and secret" or "No secret"; under the cards the note on why "Acts as" matters. Continue goes to step 2; Back and Change return to step 1 and keep what was typed. Nothing is written before Create.
- Step 2: the head takes the picked card's tile and title with Change; Application name and Description side by side, the automation note, the permission picker (`application-access-permission-picker.md`), and a closed "Advanced" (IP list and rate limit). The footer: Back, "Nothing is saved before Create.", Create.
- Step 3 opens by itself on the request after Create or a new secret: the one-time values and the starting point's guide (`application-access-drawer-guides.md`). Since AA3.

## Which starting points are there and what do they tick?
- `api_app_templates($groups, $contract_scopes, $money_scopes, $oauth_app)` (`includes/api/app_templates.php`, pure but for `lang()`), in order:
  - AI assistant with sign-in: kind `oauth`, nothing ticked, no secret.
  - Automation platform: kind `server`, `api_app_start_choices()` of the automation contract (`application-access-automation.md`).
  - Own integration: kind `server`, nothing ticked.
  - Read-only reporting: kind `server`, the read of every row that has one, no write.
  - Mobile app and team devices: kind `device`, nothing ticked, no secret.
- No card ticks a row whose scope, or the read its write holds, has a `moves_money` route: `api_app_money_scopes(api_schema())` and `api_app_template_safe_choices()` drop it (tests/api_app_templates_test.php).
- Agent in a channel (after the sign-in card, only while the Workspace is on, for an administrator): kind `server`, the Workspace row with write (`application-access-agent-tab.md`).

## What does the sign-in card do once OAuth is set up?
- The site serves one OAuth application (`pg_oauth_app()`). When it exists the card shows "Set up already" and Next reads "Open it": the sheet closes and that application's drawer opens. No second OAuth application is made.

## What does the create branch check?
- `start` is read with `api_app_template_posted()` against the cards and decides the kind; a posted `kind` counts only without a valid `start`.
- Name, Description and Allowed addresses go through `api_app_text_too_long()`, the addresses through `api_app_ip_allowlist_bad()`, the rate through `api_app_rate_limit_in()`, as the drawer's Advanced row; a refusal writes nothing. Addresses and rate are written right after `api_app_create()`.
