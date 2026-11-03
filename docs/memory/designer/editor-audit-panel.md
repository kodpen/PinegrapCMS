---
name: editor-audit-panel
description: Editor audit panel Accessibility section: rows per registry concept, the rendered draft's a11y findings, canvas checks under them, badges via data-pg-node
---

## What does the panel show?
- HTML (the canvas checks of `validateTree()`), SEO (`designer/seo_check`), and Accessibility: `_renderAuditConcepts()` in `style_designer.src.js`, filled from the same `seo_check` answer.
- One row per concept of the audit rule registry (`platform/audit-rule-registry.md`, sent as `concepts` by `pg_seo_check_concepts()`) that the rendered draft's accessibility checks found or, for a concept with an a11y code, a canvas check found: label (the report's wording, else SEO's, else the canvas finding's), WCAG criterion and level, SEO severity, occurrences and the first example; the canvas findings of that concept under it with Go.
- A page not saved yet has no rendered draft (`a11y: null`): the section says the checks run once it is saved; the canvas rows still group.

## Where do the accessibility findings come from?
- `seo_check` in document mode runs `pg_a11y_check_html()` on the same render the SEO structure rules read (`seo-check-draft-render.md`) and answers `a11y`: `pg_seo_check_a11y_rows()` (concept, code, label, severity, criterion, level, occurrences, detail, nodes).

## How do findings badge the canvas?
- The draft render runs with `pg_designer_audit_render(true)`: `_render_tree_node()` writes `data-pg-node="<_id>"` on every element of the page tree and of every widget and shared component drawn into it. Never on a stored or served page: the flag is off outside `pg_seo_check_draft_render()`, which resets it in `finally`.
- Each accessibility rule passes its element through `pg_a11y_example($element, $text)`; the nearest element at or above it with `data-pg-node` is noted, and the finding gets `nodes` (at most 50). Without marks (the nightly pass) the finding keeps its stored shape (code, severity, occurrences, detail).
- The editor turns the nodes into `_sdServerAuditIssues` ({node, label}) through `_findNodeAnywhereById()`; `applyIssueBadges()` draws them with the canvas findings. An edit clears them (stale) until the panel asks again.

Test: `tests/audit_panel_test.php`.
