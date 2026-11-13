---
name: main-menu-automation-flows-row
description: Main menu row 26, Automation flows (AF4, AF5, #9169): its place at the top, its gate (the Flows screen's), the quiet count of flows running, the screen it lights, icon A and its motion
---

## Where does the row sit?
- Slot 26, the next free one, in the untitled block of `pg_menu_categories()`: Dashboard 0, Automation flows 26, Workspace 23, My Notes 24. Owner (2026-11-12, #8961, `workflow/decisions-made-workspace-20.md`): at the top between the Dashboard and the Workspace, named "Otomasyon akışları" (key `Automation flows`).
- One screen, `workspace_flows.php`, so no panel. The Workspace list keeps its link to it, labelled Automation flows, the tile's short name Flows.

## Who gets it?
- `pg_flows_menu_row($user)` (`includes/fn/automation_flows.php`) asks `pg_flows_user_may($user)`, which the dashboard's flow entries ask too (`dashboard-v2-flows-card.md`), and asks what the screen's `ws_screen_gate($user, true)` asks: `pg_flows_enabled()`, `ws_ready()`, a member of the team (`ws_rights()`: roles 0-2 unless their profile leaves them out, role 3 with the Workspace right). Not the Workspace switch: the row shows with it off.
- Every panel page draws it, so with the Workspace off only `includes/workspace/access.php` is loaded for the gate, and the whole module (about 7.7 MB of PHP, some 100 ms to compile without opcache) only while `pg_flows_runs_open()` finds an open run.

## What is the number beside it?
- `ws_flows_running_count($viewer)` (`flow_runs.php`): flows with a run running or waiting (`ws_flow_run_open()`) that the person may read (`ws_flow_run_access()`), each flow once. The newest 500 open runs are read without their context; the context only for flows the person does not edit.
- Waiting counts: such a run (an approval, a reply, a time) is still under way, and running alone lasts seconds, so a page drawn by the server would almost never show it.
- `pg_menu_live_count()`: a dot in `--pg-success-ink` and the number in `--pg-fg-2`, not the red pill (nothing to act on); "{var:1} flow(s) running now" as its hover and for a screen reader; on the rail the dot alone on the icon's corner; zero draws nothing. The dot pulses as in the owner's drawing, only where motion is welcome. Counted as the page is drawn, never refreshed in it.

## Which screen does it light?
- `workspace_flows.php` marks row 26 (23 where the row is not built). There the Workspace list does not open by itself and the screen is no navigator screen (`$ws_side_screen` cleared while the row exists); a panel kept from the list's own Flows link still reopens.
- The screen's title and heading, the list's label (`fl_nav`) and the Workspace tour's step say Automation flows; sentences keep "flow". The Dashboard's Flows card keeps its name and the Workspace's gate.

## How does icon A move?
- `pg_menu_icon('flows')`: start circle, step box (`.pg-mi-step`) and end circle in `workspace-color`; the path and the dot (`.pg-mi-dot`) in the sky accent. The dot rests in the end by its `transform` attribute.
- `backend.src.css`: hover plays `pg-mi-flow` (0.9 s: the dot runs the path, the step bounces); `[data-pg-flows-running]` on the row plays `pg-mi-flow-round` (4.8 s: three quarters travelling, a rest in the end), both inside the no-preference block. The keyframes are points of the path at their share of its length (translate, not `offset-path`, which SVG elements do not take everywhere); `tests/automation_flows_menu_test.php` checks them.

## How was it checked?
- `tests/automation_flows_menu_test.php`; the sandbox with the Workspace off and on: the row's place, a waiting run counted (1, as the list's Waiting), the row lit on the screen with the list shut, the rail's dot, the phone drawer, both themes, no movement under reduced motion.

Related: docs/memory/ui/main-menu-categories.md, docs/memory/ui/main-menu-icons.md, docs/memory/workspace/automation-flows-screen.md
