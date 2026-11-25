---
name: sketch-build-again
description: "Build again" of a built sketch page (SK4): which sections stay, are built again, added or removed, when it asks, the History snapshot first, what was not done
module: designer
---

## What does "Build again" do?
- In the part `sketchbuild` (`designer/sketch-build.md`), on the draft found by the uid kept in its root.
- `plan()`: a section is matched by its id (its first box's). Unchanged or edited with the same box: stays; changed, not edited: built again; edited and changed: stays unless its tick is on; new: added; gone: removed. Header and footer stay. Edited = the node's `nodeFp()` (no `_id`, `_expanded`) differs from the one kept at the build.
- Asks (the question, blue tile, filled button, Design examples "Confirm with its effects, undone by History") only for a removal or a clash; "More" lists the clashes with "Build … again from the sketch". Enter builds, Escape cancels.
- First the built page's tab opens and a History snapshot "Before “Build again” of …" is written and read back; if it is not there nothing is built. Then one undo step; nodes added in Design stay after the section they followed (`arrange()`).

## What was not done or checked?
- Not run in a browser (no running site): the pure parts and a Node run of a first build and a "Build again" with a fake host. "In the menu" of "Page details" is not written to a menu; the details window still edits the sketch's values after the build. The zoomed-out head and the More menu's "Build the page" row (B8) are not built. Tests: `tests/sketch_build_test.php`.
