---
name: designer-closed-blocks
description: Plan (owner 2026-11-13, #9076): the components the designer canvas still draws as one closed block are listed, then opened into editable element trees; steps CB0-CB2
---

## What is asked?
- Owner (2026-11-13, #9076): "şu anda birçok bileşen yer tutucudan ibaret. […] kullanıcılar gerçek sayfa bileşenleri tasarlamayı bekliyor olacak."
- Asked what that covers he chose "İkisi de": the sample values (`plans/designer-canvas-real-samples.md`) and the closed blocks, this plan. Record: `workflow/decisions-made-product-48.md`.

## What is a closed block?
- Something the palette, a design template or a moved classic page puts on a page and the canvas draws as one box the user cannot open: its inner elements cannot be picked, styled, moved or deleted one by one.
- Known kinds: a classic page's system region (a read-only preview, `designer/legacy-region-preview.md`; a custom form's has "Convert to design elements", `designer/layout-form-converter-editor.md`), a `custom_php` node, a component drawn per request (`designer/language-switcher-component.md`), a system component whose server markup has no element tree.

## Which rules does every step keep?
- An opened block becomes a system widget tree on data bindings (`designer/system-widget-bindings.md`), its output compared with the page it replaces (`designer/system-widget-change-checklist.md`). A published page keeps its output until its user converts it.
- Output that depends on the request stays a placeholder in the saved body (`designer/language-switcher-component.md`): such a block gets real settings and a true drawing on the canvas, not a stored tree.
- No schema change inside a step; a need comes back as an `architect` question.

## Which steps?
- CB0 (S, no product code) The list: every palette component, system widget type, page type and e-mail the canvas draws as a closed block: what it prints on the site, what a user can change in it today, which design templates and page types use it, and what opening it takes (a tree, a converter, settings only). Into a new file of `designer/` named canvas-closed-blocks, with a proposed order by how often a user meets the block.
- CB1 (architect) The list and the order go to the owner; the steps from CB2 on are written from his answer, one block family per step.
- CB2 and on: one family per step, by the rules above.
- Close-out: the lasting part of the list stays in `designer/`; this plan is deleted.

## In which order?
- CB0 may start at once, beside CS0 of `designer-canvas-real-samples.md`; nothing else before CB1. Claim as `[plan:designer-closed-blocks] CB0 — the list of closed blocks`.
