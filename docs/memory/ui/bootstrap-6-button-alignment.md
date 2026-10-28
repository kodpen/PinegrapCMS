---
name: bootstrap-6-button-alignment
description: Shell rule .btn:where(.text-start|.text-end) in backend.src.css: why text-start buttons came out centred under Bootstrap 6, how the rule keeps utilities winning, the ellipsis limit
---

## Why were text-start buttons centred?
- Bootstrap 6's base button rule (`.btn, .btn-link, …` in the components layer) is `display: inline-flex; justify-content: center`. v5's button was inline-block, so `text-start` (text-align) left-aligned its label; on v6 text-align does not move flex content.
- Symptom: the Workspace message context menu (Yanıtla, Görev yap, … Sil) and about 170 other `.btn … text-start` places (menu items, pickers, full-width list buttons, sidebars) showed their labels centred.

## What is the fix?
- One shell rule atop `@layer custom` in `assets/css/backend.src.css`, next to the other Bootstrap 6 shell rules (`bootstrap-6-codemod.md`): `.btn:where(.text-start) { justify-content: flex-start }` and `.btn:where(.text-end) { justify-content: flex-end }`. Pinned by `tests/bs6_button_alignment_test.php`.
- `:where()` keeps the specificity of `.btn` alone, so a panel rule on the button's own class (later in the file) and the `justify-content-*` utilities (utilities layer) still win.
- Every panel `text-start`/`text-end` button carries `.btn`; no `btn-*` variant is used without it, so the rule names only `.btn`.
- Sites keep Bootstrap 5.3.8 and never load backend.src.css; they are not affected.

## Does text-truncate still work on such a button?
- It clips (overflow hidden), but a flex container draws no ellipsis on its anonymous text item. A label that needs `…` wraps its text in an inner `<span class="text-truncate">`. This predates the rule; v6 centred and clipped both sides before it.

Related: docs/memory/ui/bootstrap-6-codemod.md, docs/memory/ui/bootstrap-6.md, docs/memory/ui/panel-css-tokens.md
