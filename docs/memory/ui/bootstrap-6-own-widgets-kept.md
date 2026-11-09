---
name: bootstrap-6-own-widgets-kept
description: Own panel widgets kept beside Bootstrap 6 on purpose (multiselect, number buttons, empty states, callouts, glass theme…) and three swaps no step holds: guest toast, .ws-chip, role cards
---

## Which own widgets stay as they are?
- jquery-multiselect-checkbox (owner, `workflow/decisions-made-panel-3.md`), the number +/- buttons, empty states, callouts, segmented controls, the settings progress line, the glass widget theme and the own show/hide bindings. A matching Bootstrap 6 component is no reason to swap them.
- The swaps that were made, with the same look: `bootstrap-6-own-widget-swaps.md`.

## Which swaps were left, and why?
- No step holds them since the edit screen redesign closed (2026-11-06). A swap is made only where screenshots show no change, and none of these keeps the screen as it is:
  - Guest page toast (`workspace_guest.js`): the page loads Bootstrap's CSS but none of its JavaScript; a Toast would add the whole bundle for one notice.
  - `.ws-chip` to `.badge-subtle`: equal only by overriding every badge token (min-height, weight, line-height, gap, radius), and chips inside saved message HTML keep needing `.ws-chip`, so nothing is removed.
  - Role cards (`.pg-role-card`, `user_permissions.php`) to `form-field-card`: v6 shows the radio and puts it inside the card; today the radio is hidden (`btn-check`) and the label is the card.
