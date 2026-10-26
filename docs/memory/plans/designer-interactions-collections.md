---
name: designer-interactions-collections
description: Plan (approved 2026-10-19, watch list, designer session): interaction/animation builder for designer nodes and typed collections with relations and dynamic pages beyond forms-as-collections
---

## Why?
- The designer has no interaction or animation builder; the canvas only pauses or plays animations a design brings itself (`assets/js/style_designer.js:6464`). Collections exist only as custom forms with list/detail widgets (pretty URLs, filters, RSS, comments) without typed fields, relations or taxonomies. Every compared builder ships both; they are the two biggest designer-side gaps and belong to the running designer session, which decides the order and the slices.

## What is decided?
- Both items are recorded here so the designer session can pick them up; no slices are fixed by this file. Everything stays additive: palette components keep the "no custom JS, Bootstrap 5.3.8 only" rule (`docs/component-development-guide.md` §0), so interactions are produced as data attributes read by one small front-end script, not as per-component JavaScript.
- Interactions (proposed shape): per node an "Interactions" tab with triggers (page load, scroll into view, hover, click) and effects (fade, slide, scale, stagger for children, sticky, parallax light), timing and easing; stored in the node JSON (`_fx`), rendered as `data-pg-fx-*` attributes and driven by `assets/js/pg_fx.src.js` (+ `.min.js`), honouring `prefers-reduced-motion`; the canvas preview uses the same script. No GSAP; CSS transitions and IntersectionObserver only.
- Collections (proposed shape): a `collections` layer over the existing form engine rather than a new engine: a collection = a form with a schema flag, typed fields (text, rich text, number, date, image, file, reference to another collection or to products/pages, option set), slug field, taxonomy (tags/categories as reference collections), dynamic list/detail widgets reuse `form_list_view`/`form_item_view` with reference resolution and taxonomy filters; RSS, JSON-LD and translation keep working because the storage is still form records. Templates (blog, menu, events, staff) migrate to collections only by adding the flag; nothing breaks for existing sites.
- Legacy: classic pages and legacy form pages are untouched.

## What remains?
- Interactions: cut into steps I1–I4 in `plans/designer-interactions-steps.md`.
- Collections: cut into steps C1–C7 in `plans/designer-collections-steps.md`.

## Open questions?
- Should collection items get their own revision history like pages? Yes, through the same `page_revisions` pattern (`record_revisions`), decided when the collections slice starts.

## Where to start?
- Hand this file to the designer session; read `docs/memory/designer/custom-form-*.md` and `system-widget-bindings.md` first.
