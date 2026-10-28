---
name: designer-ai-known-class
description: How the designer AI decides a class is one the framework ships (pg_design_ai_known_class()): the Bootstrap 5 vocabulary, state/pg-*/Bootstrap Icons classes, custom designs, the class-family fallback
---

## What is it for?
- `pg_design_ai_known_class($word, $framework)` in `includes/designer_ai.php` answers "does a stylesheet the page loads define this class?". `pg_design_ai_bare_classes()` lists the classes a proposal adds that neither it nor the page CSS defines (fed back to the assistant); `pg_design_ai_orphan_classes()` lists page-CSS classes nothing uses.

## How does it decide?
- `custom` framework: never known (no framework CSS).
- Always known: `active`, `show`, `fade`, `disabled`, `collapsed`, `pg-*` (the site's own), `bi` and `bi-*` (Bootstrap Icons, a separate sheet).
- `bootstrap5`: exactly the class names of `assets/js/designer_vocab/bootstrap5.json` ([[designer-bootstrap-vocabulary]], Bootstrap 5.3.8, the site copy, not the panel's Bootstrap 6), read once per request by `pg_design_ai_vocab_classes()`. So `text-neon`, `card-glow`, `btn-primry`, `d-flx` are unknown.
- Missing or unreadable vocabulary, or another framework key: `pg_design_ai_known_class_family()`, the old prefix regex, which lets a made-up name of a known family (`text-neon`) through.

## Why the switch?
- The prefix regex alone let invented names pass and missed real ones; decided in #2439 as the last step of the smart completion work.
- `pg_design_framework_key()` maps every stored value to `bootstrap5` or `custom`, so only those two reach it today.

## How is it tested?
- `tests/designer_ai_known_class_test.php`: real, state, icon and site classes, invented and misspelt ones, `custom`, the fallback with a missing file.
