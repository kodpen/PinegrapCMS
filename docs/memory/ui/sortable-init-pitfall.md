---
name: sortable-init-pitfall
description: jQuery UI .sortable() on a panel screen: backend.src.js now sets up only lists marked .pg-sortable; before, its ready handler rewrote the options of every sortable on the page.
---

## Does `backend.src.js` touch a sortable a screen sets up itself?
- No longer. Its ready handler sets up only lists that opt in with the class `pg-sortable`, with its own defaults (`items: "a"`, `axis: 'y'`, `delay: 300`, `containment: "parent"`, `helper: 'clone'`, `scroll: false`...). A list a screen makes sortable itself keeps the options it was given. No screen uses `pg-sortable` today.
- Before, the handler ran `$(".ui-sortable").sortable({...})`. `ui-sortable` is the class jQuery UI itself adds to every sortable it has built, and `.sortable({...})` on a live widget does not build a second one, it OVERWRITES the options. Symptoms: nothing could be dragged (`items: "a"` matched nothing) or an interrupted drag left a COPY in the list (`helper: 'clone'`).

## What is left from that time?
- `assets/js/product_builder.js` still installs its image tiles inside `$(function(){...})` with every option spelled out, and its comment still describes the old handler; harmless.
- Never add a global `.sortable()` call on `.ui-sortable` (or any class jQuery UI sets) again: it reaches lists other screens configured (dashboard widgets, product images, menu items).

Related: docs/memory/ui/panel-layout-pitfalls.md
