---
name: design-template-launch-pages
description: Launch template's camera, specs and compare pages on the Product Launch blocks: the spec comparison from $spec_groups, the differences-only compare, motion, what was dropped
---

## How are camera, specs and compare built?
- `camera`: `$launch_camera(true)` as its top (h1, parallax, on to `specs#spec-camera`), `$launch_showcase($camera_panels, …)` (sticky photo, a panel per way of shooting, `#camera-<key>` search sections), sample photos revealed with stagger, the camera rows of the spec comparison.
- `specs`: one `launch-specs` table of every group (`#spec-<key>` row groups, search sections, the jump list's anchors), then `$launch_box` (`#in-the-box`). A still page.
- `compare`: model heads revealed with stagger, the comparison with `'differ' => true` (only the rows whose values differ), then "What every Orbit shares" from the rows the three share: one source, a changed figure moves its row by itself.
- `$spec_table($group_keys, $opts)` in `parts.php` draws the `launch-specs` structure (head cells with the "From" price, `scope="rowgroup"` groups); `$opts`: `models`, `differ`, `anchors`, `caption`. `$feature_band`, `plx-hero` and `plx-specs` were dropped in 2.0.0.

## Why does compare show only the differences?
- The specs page already has every row side by side; compare answers "which one": the rows whose values differ, and the rest as one shared list. Both come from `$spec_groups`, so no row is written twice (`test_launch_spec_table_differences`).

## How is it tested?
- `test_launch_assembly_home_and_features_on_launch_blocks` (`tests/design_template_launch_assembly_test.php`) covers the five pages: rules shipped, motion (camera: parallax, sticky, five reveals; compare: the heads; specs: still), the spec comparison on camera, specs and compare, no `plx-hero` / `plx-specs`.

Related: docs/memory/designer/design-template-launch.md, docs/memory/designer/designer-blocks-launch.md
