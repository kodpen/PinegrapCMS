---
name: workspace-flows-buttons-look
description: The Flows screens' buttons in look A (WL3, #11360): which rank each part takes in workspace_flow.js and workspace_flow_flag.js, the filter chips as pg-seg, the compact run flag, what was left
---

## What changed?
- WL3 (#11360): the 56 `btn btn-` buttons of `assets/js/workspace_flow.js` and the 3 of `workspace_flow_flag.js` take look A's recipes (`ui/look-workspace-recipes.md`); both files count 0 old buttons in `tools/check_look_baseline.json`. Nothing a button does, says or where it sits changed.
- A labelled button is built with `K.lookButton()` (no `me-1` on its icon, the button's gap spaces it); an icon-only one with `K.button(…, '', icon, name)`, so it keeps its `aria-label` and `title`.

## Which rank does each part take?
- Fill (one per view): "New flow" on the list's bar (`BAR_FILL`), the AI sketch's Go, the template setup's Publish, the template dialog's Save, the share dialog's Share.
- Quiet small: the template cards' Use (a card's actions share one rank), Quick setup, Edit on the bar, Try, the Add buttons of constants, web fields, row filters, AI classes, palette buttons in the outline, Reload, Close run, a failed step's Retry and Skip, the failure reason's buttons, the run's task links (`taskLink()`, an `<a>`). The connect row's Add stays full height beside its select.
- Ghost small: Back (`viewBack`), Cancel, every Retry link, Hide, Show more runs, All runs, a run's Show/Hide template, a change's Remove, the thread's short Remove, the AI marks' box links (`.wsf-ai-go`).
- Danger small: Remove box, Remove arrow, Remove thread, Cancel run (they were `theme-warning` outlines).
- `pg-icon-button pg-icon-button-sm`: every × Remove of a row, the template card's Edit and Delete, the setup panel's Close (named "Close" now; it had no name), the share row's Remove, the run customer's Clear, the flag's fold chevron.

## How are the list's filter chips drawn?
- As the segmented control: `ui.chips` is `pg-seg pg-seg-wrap`, each chip a `pg-seg-item` with `aria-pressed` and an `em.pg-seg-count`. The old `.wsf-chips`, the list's `.wsf-chip.active` and `.wsf-chip-count` rules are gone; `.wsf-chip` is now only the value chip of a field.

## Why does the run flag keep a compact override?
- The flag (`.wsf-flag`, 12 px text on its state tint above the last box) holds Stop, the fold chevron and Open run. `.wsf-flag .pg-button, .wsf-flag .pg-icon-button` sets height and width auto, padding `0 .25rem` and inherits colour and size, as `.wsf-flag .btn` did: a 28 px button would double the flag's height and a grey text breaks its tone.

## What was left?
- The two dialogs' × (`btn-close`, template and share dialogs): not an old button by the look check and Bootstrap 6's own close.
- Fields (`form-control`, `form-label`) of these files: WL9.
