---
name: widget-parts-anywhere
description: W17 parts anywhere (#3074): a read-only part of the page's record placed outside its detail widget, drawn by that widget's renderer in the same request
---

## What is it?
- A node outside any widget with `props.partOf` = the shared id of the page's detail widget (product, event or form record view). Its subtree is drawn by that widget, from the same record, where the node stands. Decided on #3074 (option b).
- Read-only parts only (price block, gallery, breadcrumb, rating, stock line). Parts with controls stay inside their widget.

## What is left?
- W17b: editor placement and canvas preview (`style_designer.src.js`).
