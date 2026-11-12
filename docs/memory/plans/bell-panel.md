---
name: bell-panel
description: Plan (owner 2026-11-11): the bell as a drawer from the right, its rows after the dashboard's Next up rows, module chips, unread only, asked of me, search, day headings; read when opened; steps BP1-BP4
---

## What is it for?
- Owner (2026-11-11): "bildirim panelini geliştirelim daha fazla filtre ekleyelim ve bildirim kartlarını daha kullanışlı hale sokalım. bildirim kart görünümü yeni pano ile eşleşsin istiyorum 2. görsel sadece örnek".
- After BP2 (#8722) put the dashboard's row into the 26rem dropdown he did not accept it (#8754): "daracık alanda boyle bir panel garip olmuş … yeni panodaki gibi modernize olsun istiyorum demiştim ama bu panel olmamış".
- His answers: `workflow/decisions-made-panel-4.md`.

## How does the panel open?
- A drawer from the right edge (owner, #8754: "Sağdan tam boy çekmece"), full height, about 30rem wide, the whole width on a phone. Not chosen: the dropdown widened.
- Its head: the name, a close button, "Mark all as read", a menu holding "Delete All Notifications"; then the filters; the list alone scrolls. The bell, Ctrl+Q, Escape and a click outside open and close it.

## What does a row look like?
- After the dashboard's Next up row (`ui/dashboard-v2-drawn-look.md`), laid out for the drawer: line one the module tag as a small mark, the unread dot and a short title (who and what); line two the place and the time, with the one button named by the job and the row's menu (Delete, "Mark as unread") at the right; then the excerpt, two lines at most. Day headings: Today, Yesterday, Earlier.
- Five modules from the row's `action` (`ui/bell-row.md`): Shop, Accounting, Content, Team, System. The tag's colours are the dashboard's (`--pg-bell-tag-*`); a test holds them equal.
- The row's surface, text and lines use the panel's own tokens, never a colour of the bell's own (owner 2026-10-30, `workflow/decisions-made-workspace-5.md`).
- A flow's runs stay folded ("+N more"), a refusal keeps its toast (`ui/bell-refusals.md`), a link opens only what the reader may use (`ui/notification-links.md`).

## Which filters are there?
- Module chips in place of Site and Workspace, for everybody: All, then a chip for each module that has a row, with its unread count. `pgBellOpen('workspace')` and a kept `workspace` choice mean Team; a kept `site` means All.
- "Unread only": a switch that works together with the chosen chip.
- "Asked of me": Workspace rows of kind `mention`, `question_asked`, `approval_requested`; the chip shows only while such a row exists.
- A search box over the title and the second line. The list is loaded whole, so every filter works in the script; no new query.

## What does the button do, and when is a row read?
- The button opens the row's place and is named by the job: Reply, Show, Join, Approve. Where the dashboard acts in place the row does too, through the same panel actions (step BP4): Approve, Try again, a task done, Join. No new kind of action and no reply box.
- Opening the bell reads nothing; a row is read when its button or title is used or its dot is pressed (`ui/bell-read-rule.md`). Read in the Workspace stays read in the bell (`ws_bell_sync_read()`).

## What does every step keep?
- No schema. `ws_inbox` stays the store for the API, sync and push; the API's `group` filter stays, a module filter there is not part of this plan.
- Every text through `lang()` and the run-time dictionary (`$translate` in `includes/fn/output.php`), keys by `php tools/tr_add.php`; the bundles are rebuilt (`php tools/build_js.php`, `build_css.php`).
- Looked at in the sandbox (`ui/panel-screenshots.md`): wide and phone, light and dark, before and after, linked in the PR: a unit test cannot reach the panel's layout. BP2b's pictures are approved by the owner before it merges; the look on dev is his, after landing.

## Who holds it, where does it start?
- Coordinators hand the steps out; claim as `[plan:bell-panel] <step> — <topic>`. The steps: `bell-panel-steps.md`. BP4 is next.
