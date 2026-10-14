---
name: sortable-init-pitfall
description: Pitfall installing jQuery UI .sortable() on a panel screen: backend.src.js overrides options of every ui-sortable; init in $(function(){}) with all options.
---

## What does `backend.src.js` do to every sortable?
- At `backend.src.js:446` the ready handler runs `$(".ui-sortable").sortable({items:"a", handle:this, axis:'y', delay:300, containment:"parent", helper:'clone'})`.
- `ui-sortable` is a class jQuery UI itself adds. The line finds EVERY installed sortable on the page, not lists marked in markup. On a live widget `.sortable({...})` does not create a second widget, it OVERWRITES the options. No hand-written `class="ui-sortable"` exists in the codebase.

## How do you install a sortable on a new screen?
1. Install inside `$(function(){...})`. Ready callbacks run in registration order; `backend.src.js` loads first, so its handler runs first and yours writes last. Installing immediately (at parse time) gets you overridden.
2. Write EVERY option that line touches explicitly: `items`, `handle`, `axis`, `delay`, `containment`, `helper`, `scroll`, `dropOnEmpty`, `revert`, `tolerance`. An option you omit keeps the value it gave.

## Why do legacy product/group screens survive?
- They install inline with `$(".sortable-list").sortable({...})`, saved by accident (registered later in the ready queue).
- Symptoms of falling into the trap: dragging does NOT work at all (`items:"a"` matches nothing), or an interrupted drag leaves a COPY in the list because of `helper:'clone'`.
