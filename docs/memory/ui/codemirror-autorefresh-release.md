---
name: codemirror-autorefresh-release
description: Call window.pgReleaseEditors(scope) before replacing or closing markup that may hold CodeMirror editors; autoRefresh polls hidden editors every 250 ms forever.
---

## Why must editors be released before their markup is dropped?
- CodeMirror's `autoRefresh` addon polls a hidden editor every 250 ms and re-arms itself as long as the answer is "still hidden". If an editor will never be visible again (markup replaced, window closed), the polling **lasts until the tab closes**.
- The addon also binds `mouseup` and `keyup` on `window` and never removes them: each abandoned editor adds one more handler per click.
- **`toTextArea()` does not stop this.** The addon listens to the **option**, not to destruction; after the editor is destroyed it keeps reading `offsetHeight` of a wrapper no longer in the document.

## What is the rule?
- Any code that changes or closes a region that may carry an editor first calls `window.pgReleaseEditors(scope)` (`backend.src.js`). It does `setOption('autoRefresh', false)` on every `.CodeMirror` instance in the scope - that is what the addon listens to.

## How do I recognise and measure the leak?
- The symptom is not an error and is not found by searching: the tab keeps stuttering (the compositor is idle), clicks and the context menu stall (they need the main thread), Chrome does not say "page not responding" (no single long task, a constant stream of short ones).
- Measure: hook `setTimeout`, then count timers per second **after** something was opened and closed; if the count grows linearly with the rounds, there is a leak.
- Same class as the Chart.js rule: markup that holds a live object cannot simply be thrown away. See docs/memory/ui/chart-destroy-before-markup-change.md
