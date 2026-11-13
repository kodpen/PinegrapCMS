---
name: automation-flows-menu
description: Plan (owner 2026-11-12, #8961): Flows become "Automation flows", a main menu row with a moving icon, and run on a site whose Workspace is off; the in-channel view stays; steps AF0-AF6
---

## What is asked?
- Owner (2026-11-12): "önemli her şeyi workspace altına tıktık ama açılmadığında akış ve birçok özellik kapalı kalacak. akışları ana menüye taşıyalım ne dersin? animasyonlu bir ikonu olsun adını da otomasyon akışları olarak güncelleyelim." Record: `workflow/decisions-made-workspace-20.md`.

## What did the owner decide?
- Ya: the row sits at the top of the main menu, between the Dashboard and the Workspace, with the count of flows running now beside it. Drawn in `plans/automation-flows-menu/menu-row.html` (it moves when opened) with its pictures.
- The icon is drawing A: a start, a step and an end on a path, a dot following the path. It moves once on hover like the other rows and slowly while a flow runs; never on a device that asks for less motion (`ui/main-menu-icons.md`).
- The name is "Otomasyon akışları" on the row and the screens' titles (English key "Automation flows"); inside a sentence "flow" stays.
- M1a: flows are on at every site, behind the right only, also where the Workspace is off. No switch of their own.
- The view of a flow that opens inside a channel stays in the channel and takes the flow window's new look.

## What is there today?
- `ws_enabled()` asks `WORKSPACE_ENABLED`. With it off the Flows screens refuse and `job.php` advances no flow (its flow pass asks `ws_enabled()`), so an order's message flow would not run. Store, runner and tables are the Workspace's (`workspace/workspace-flows-overview.md`).

## Which rules does every step keep?
- One gate function for flows (the schema is ready; no module switch), used by the screens, the job's pass, the API routes and the events that start a flow. The Workspace's own gate stays for everything else; no `ws_` screen reaches a site member.
- A box or start that needs the Workspace (a channel message, the task boxes, check-in, a customer brought into a channel, the mention and join starts, an agent) is offered only while the Workspace is on. On a site without it a flow holding one shows a problem and that box does not run. The list of those kinds lives in one place beside `ws_flow_kinds()`.
- Who may see and make flows stays as it is (`workspace/workspace-flows-decisions.md`); the row follows its screen's gate (`ui/main-menu-entry-gates.md`).
- Tables and function names stay: no rename, no schema.
- The row takes a slot of its own in `pg_menu_categories()`, never a retired number (`ui/main-menu-categories.md`). The Workspace list keeps its Flows link, to the same screen.

## Which steps?
- AF0 (S, no product code) The census and its proposals. Done #9109: `automation-flows-menu-census.md`; decided in `automation-flows-menu-steps-2.md` (AF3b, AF3c, AF4b, AF4c).
- AF1 (M) The gate and the job: flows advance and their events arrive with the Workspace off; tests. Done #9109 (`workspace/automation-flows-gate.md`, `workspace/automation-flows-workspace-boxes.md`).
- AF2 (M) The screens outside the Workspace shell: the list, the window and the run view open from the main menu with the Workspace off. Done #9154 (`workspace/automation-flows-screen.md`).
- AF3 (S) The boxes that need the Workspace: offered only with it; the problem line. Done #9154 (`workspace/automation-flows-workspace-boxes.md`).
- AF4 (S) The menu row: slot, gate, name on the row and the titles, the running count. Done #9169 (`ui/main-menu-automation-flows-row.md`).
- AF5 (S) Icon A in `pg_menu_icon()` with its motion. Done #9169 (`ui/main-menu-automation-flows-row.md`).
- AF6 (S) Close-out: the lasting rules into `workspace/` and `ui/`, this plan deleted.

## In which order?
- AF0 to AF5 and AF3b (#9225) are done; then AF3c, AF4b and AF4c in any order, then AF6. `messages-area-steps.md` turns campaign profiles into flows only after AF3 has landed.
- Claim as `[plan:automation-flows-menu] <step> — <topic>`.
