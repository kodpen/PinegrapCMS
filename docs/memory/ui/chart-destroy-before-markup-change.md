---
name: chart-destroy-before-markup-change
description: Destroy Chart.js charts (destroy_widget_charts in welcome.php) before .html()/.remove()/.append() replaces markup with canvases, or instances leak.
---

## Why must charts be destroyed before the markup changes?
- Chart.js keeps every chart in its own registry (v4: `Chart.instances`, key = canvas). Removing the canvas from the DOM does **not** remove the chart from it: the instance keeps its `ResizeObserver`, its data and everything it brought alive, and on every window resize the whole list is walked.
- The panel (`welcome.php`) throws away and rewrites widget bodies once a minute. A tab left open 50 minutes held **58 charts for 7 canvases**, 51 drawing onto a detached canvas, 6 accumulating per round. The symptom reads as "tab freezes" but the main thread is not locked - it is constantly busy.

## What is the rule?
- Any code that replaces a region that may hold charts with `.html()` / `.remove()` / `.append()` first closes the charts in that region. `destroy_widget_charts($scope)` in `welcome.php` does this (`Chart.getChart(canvas).destroy()`); a new refresh path must call it too.
- The hook was deliberately NOT put into jQuery's `remove()` (overriding `$.cleanData`): it would affect every screen and hide who owns the chart. Widget refresh is the only place that routinely replaces chart markup.

Related: docs/memory/ui/codemirror-autorefresh-release.md
