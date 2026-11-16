---
name: plan-drawers-one-look-features
description: Plan plan-drawers-one-look, features D1 to D14: the shared form parts, no Bootstrap form class, one order and one wording for shared fields, the footer, the phone, what must not change
---

## What do both drawers build from?
- (D1) One set of form parts in the Workspace kit, used by `eventForm()` and `planDrawer()`; `planDrawer()`'s own `field()` and `input()` move there. The parts:
  - a field: label `pg-field-label` bound to its control, help `pg-text-meta`, error `pg-field-error`;
  - a text input and a textarea (`pg-input`), a select in look A's recipe as the Design examples screen shows it, a date and a time field (the kit's `dateField`);
  - a pair (`ws-plan-pair`), a switch row (`ws-plan-switch`, `pg-switch`), a segmented choice (`pg-seg`), the footer's buttons.
- (D2) Neither drawer holds a Bootstrap form class after PD2: `form-control`, `form-control-sm`, `form-label`, `form-text`, `input-group`, `row` / `col-*`, `.switch`, `btn btn-*`. That counts the "New plan" drawer's four selects (Reminder, Repeat, Repeat ends, the roadmap's templates) and the unit of "Repeat every" (`input-group`).
- (D3) People and the customer are chosen with the pickers both use today (`peoplePicker`, the customer picker); they are not rebuilt.

## What is the same in both?
- (D4) The shared fields in one order: Title; First day and Last day side by side; All day; Start time and End time side by side; then the drawer's own fields; Note; Customer.
- (D5) One wording: "Title", "First day", "Last day", "All day", "Start time", "End time", "Note", "Customer (optional)". "Add to the plan" says "Starts", "Starts at" and "Ends" today; only this drawer's labels change, a key another screen uses keeps its text. Missing Turkish only with `php tools/tr_add.php`.
- (D6) A line under the header says where the item goes, same place and look in both (`ws-plan-private`: an icon and meta text). "New plan" keeps its three lines ("Only you see this plan." and the two beside it). "Add to the plan" gets one with `bi-people`; its words are on PD2's sheet.
- (D7) The footer: Delete at the left on an existing item (`pg-button pg-button-danger pg-button-sm`), Save at the right (`pg-button pg-button-fill pg-button-sm`). "Add to the plan" loses "Cancel": the drawer closes as "New plan" does, by the header's cross or Esc.
- (D8) Neither drawer scrolls sideways at 390 px or at 1440 px, light or dark; pairs stay side by side at 390. The rules of #9858 (`ui/pg-datepicker-drawer.md`, `workspace/workspace-plan-drawer-phone.md`) hold for both.

## Where do each drawer's own fields sit?
- (D9) "Add to the plan": Kind first, above Title (it decides which fields show). Who after the times, as a `pg-seg` like "Who can see" (People, Department, Company, each offered only to who may use it today), with People or Department under it. After Customer: Meeting link (the address field, "Jitsi" and "Join" on one row that wraps at 390, from `pg-input` and `pg-button`), then the Meeting note button.
- (D10) "New plan": Reminder and Repeat after the times, Who can see after them, the roadmap last. Unchanged.

## What must not change?
- (D11) "Add to the plan" behaves as before: which fields show for which kind (no times when all day or for leave and holiday; Customer only for a visit; Meeting link hidden for leave and holiday without a link), the defaults (kind meeting, 09:00 to 10:00, what a caller passes), the limits (title 255, note 500), the names and values posted to `ws_event_save` and `ws_event_delete`, the confirmation, the toast, `onDone`. No caller of `eventForm()` and not `h.planItem` is edited.
- (D12) "New plan" behaves as before: its fields, rules, posts, and the read-only state (`ws-plan-readonly`) of somebody else's plan.
- (D13) The stores stay apart: no table, column, action, API route or MCP tool changes; nothing moves between the two.
- (D14) Only look A's tokens and recipes: `php tools/check_look.php` counts do not rise; no new colour or size.
