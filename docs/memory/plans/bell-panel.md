---
name: bell-panel
description: Plan (owner 2026-11-11): the bell's rows drawn as the dashboard's Next up rows, module chips, unread only, asked of me, search, day headings; a row is read when it is opened; steps BP1-BP4
---

## What is it for?
- Owner (2026-11-11): "bildirim panelini geliştirelim daha fazla filtre ekleyelim ve bildirim kartlarını daha kullanışlı hale sokalım. bildirim kart görünümü yeni pano ile eşleşsin istiyorum 2. görsel sadece örnek".
- Today a row is tall: title, excerpt, sender, time, then two full-width lines ("Go to related page", a "Mark as unread" box). The filters are All, Site, Workspace, for team members only (`ui/notifications-one-inbox.md`, `notifications-menu.md`).
- His four answers of the same day: `workflow/decisions-made-panel-4.md`.

## What does a row look like?
- The dashboard's Next up row (`ui/dashboard-v2-drawn-look.md`): a module tag of one width at the left, the title, one second line (the excerpt or the sender, then the time), one button at the right named by the job, a dot for unread. Delete and "Mark as unread" sit in the row's menu. Day headings: Today, Yesterday, Earlier.
- Five modules, from the row's `action`: Shop (`new_order`, `order_return`, `out_stock`, `booking_refused`), Accounting (`erp_*`), Content (`form_submited`, `new_comment`), Team (`workspace`), System (`software_update`, `mail_failed`, `ops`, a custom row, anything else).
- The tag's colours are the dashboard's (`.pg-dash-queue-tag[data-module]`: shop amber, erp red, content fuchsia, team violet, System the neutral one), light and dark. The bell stands outside `.pg-dash`: the hues are written once more as `--pg-bell-tag-*` in `backend.src.css`, and a test holds them equal to the dashboard's.
- The row's surface, text and lines use the panel's own tokens, never a colour of the bell's own (owner 2026-10-30, `workflow/decisions-made-workspace-5.md`).
- A flow's runs stay folded ("+N more"), a refusal keeps its toast (`ui/bell-refusals.md`), a link opens only what the reader may use (`ui/notification-links.md`).

## Which filters are there?
- Module chips in place of Site and Workspace, for everybody: All, then a chip for each module that has a row, with its unread count. `pgBellOpen('workspace')` and a kept `workspace` choice mean Team; a kept `site` means All.
- "Unread only": a switch that works together with the chosen chip.
- "Asked of me": Workspace rows of kind `mention`, `question_asked`, `approval_requested`; the chip shows only while such a row exists.
- A search box over the title and the second line. The list is loaded whole, so every filter works in the script; no new query.

## What does the button do, and when is a row read?
- The button opens the row's place and is named by the job: Reply, Show, Join, Approve. Where the dashboard acts in place the row does too, through the same panel actions: Approve (`ws_approval_decide`), Try again (`ws_flow_step_mend`, the flow's owner only), a task done (`ws_task_status`), Join (the meeting's call). No new kind of action and no reply box.
- Opening the bell reads nothing. A row is read when its button or title is used or its dot is pressed; "Mark all as read" stands at the top; the bell's count stays until then. Read in the Workspace stays read in the bell (`ws_bell_sync_read()`).

## What does every step keep?
- No schema. `ws_inbox` stays the store for the API, sync and push; the API's `group` filter stays, a module filter there is not part of this plan.
- Every text through `lang()` and the run-time dictionary (`$translate` in `includes/fn/output.php`), keys by `php tools/tr_add.php`; the bundles are rebuilt (`php tools/build_js.php`, `build_css.php`).
- Looked at in the sandbox (`ui/panel-screenshots.md`): wide and phone, light and dark, before and after, linked in the PR: a unit test cannot reach the panel's layout. The look on dev is the owner's, after landing.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:bell-panel] <step> — <topic>`. The steps: `bell-panel-steps.md`. BP1 starts.
