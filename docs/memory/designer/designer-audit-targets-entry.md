---
name: designer-audit-targets-entry
description: Designer audit's target size (WCAG 2.5.8, 24px with the spacing exception) and redundant entry (3.3.7) hints: measured on the canvas, exemptions
---

## Where do these checks run?
- With text contrast, in `_sdCanvasAuditIssues()` (called by `validateTree()`): one canvas pass per check, each in its own try/catch, sharing `_SD_CANVAS_AUDIT_SKIP` (editor chrome), `_sdCanvasEditorBox()` and the node index. A finding goes on the nearest element a tree knows (`_sdCanvasNodeOf()`), inside shared components and widgets too. PR #642; contrast: docs/memory/designer/designer-audit-contrast.md.

## What does the target size check do?
- `_sdTargetSizeIssues()`: links, buttons, inputs, selects, textareas, summaries and ARIA widget roles (`_SD_AUDIT_TARGETS`) under 24×24 CSS pixels (canvas zoom divided out, `PgContrast.zoom()`) are flagged only when crowded: a 24px circle centred on them touches another target's box or another small target's circle (the criterion's spacing exception). Label: size and "at least 24×24px, or room around it" (rule `target_size`).
- Exempt as in the criterion: a link in running text (inline, with other words in its block; editor boxes looked through, `_sdTargetInSentence()`), the browser's own checkboxes and radios (`appearance` not `none`), `.stretched-link` (its target is the whole card), hidden, disabled and visually hidden ones.
- Bootstrap's 16px `form-check-input` rows pass (28px apart on the canvas); its carousel indicators (30×23 with 6px gaps) would be flagged, which is right.

## What does the redundant entry hint do?
- `_sdRedundantEntryIssues()`: within one form, or one widget whose form the server wraps around its fields only when the page is drawn (`.sd-system-wrap`, no `<form>` on the canvas), a second field with the same purpose is flagged (rule `redundant_entry`): the same `autocomplete` value, or the same name once a `confirm`/`repeat`/`again`/`verify`/`retype`/`tekrar`/`dogrula`/`onay` part is dropped (`email` and `email_confirm`). Passwords, choices, files and buttons are not looked at; two phone fields with different names are not the same.
- `email_verify` and `email_address_verify` are looked at like any field: since PR #668 the server asks the address once (docs/memory/designer/member-email-asked-once.md), so a registration or membership layout that keeps the second field is flagged. Their exemption list went in the PR that closed the accessibility plan.

## What did the templates show?
- Store, cafe, clinic and hello templates (home page, the store's registration page too): no target size or redundant entry findings. Deliberate fixtures: 12px buttons 4px apart flagged, 30px apart not; a 10px button touching an input flagged; `email` + `email_confirm` flagged, passwords, `phone`/`mobile` and shipping/billing `autocomplete` not.
