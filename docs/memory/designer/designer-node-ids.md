---
name: designer-node-ids
description: The designer's node ids (sd_N): each saved tree numbers its own, the counter kept above every loaded tree, a filled-in id never repeats one of its tree, issue badges on the issue's own node (#9958)
---

## How are node ids numbered?
- `gid()` gives `'sd_' + (++nodeIdCounter)`. Each saved tree (a page, a shared component, a system widget) holds the ids of the session that wrote it, so the same `sd_N` in a page and in a widget is normal. A template's trees carry `tpl_…` ids, which `reindex()` does not count.
- The canvas stamps every element drawn inside a shared component or widget with `data-sw-sid`, and resolves an element with `_sdFindNodeForEl(id, el)`: the widget's tree first, then the page's. Never look a node up on the canvas by its id alone.

## How is the counter kept safe?
- `_sdIdFloor()` is the highest `sd_` number in the page tree and in every tree of `_sharedCache`; `_sdRaiseIdCounter(t)` raises the counter past one tree, or past all of them without `t`.
- A shared row's tree raises it when it loads; a page load, a page switch, undo and redo set it to `_sdIdFloor()` (or the page's own count when higher). Before, only the page tree counted, so a node added inside a widget could take an id that widget already held (#9958).
- `_backfillIds(tree)` raises the counter past the tree's own ids before filling an empty one: before, a tree with sd_1 to sd_3 and a node without an id got a second sd_1.

## Where does an issue badge go?
- `applyIssueBadges()` groups the issues by node, not by id, and puts the "!" on every element `_sdIssueWrapsOf(node)` returns: those with the node's id that resolve to the node. Before, it went on the first element with the id: on a Salon page the "Request an Appointment" button's contrast warning was drawn on the booking widget's "Name" label, both `sd_25`.

## How was it checked?
- `tests/designer_node_ids_test.php` runs `reindex()`, `_sdIdFloor()`, `_sdRaiseIdCounter()` and `_backfillIds()` under Node (red on the old `_backfillIds()`) and checks the wiring.
- Sandbox: the Salon template with its booking widget saved from the editor; the warning's badge on the button after the fix, on the widget's label before.
