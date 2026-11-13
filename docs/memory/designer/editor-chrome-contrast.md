---
name: editor-chrome-contrast
description: Visual editor dark chrome text and icon contrast: --sd-* mixes for tabs, Publish, tree ids, lock icons, left panel small texts (#9099), no opacity
---

## How is the visual editor's dark chrome made readable?
- `assets/css/style_designer.css` colours the always-dark editor with its own `--sd-*` tokens; the fixes mix those, no token value changed.
- Soft text (page tabs, the "Legacy" palette badge): `color-mix(in srgb, var(--sd-text-muted) 70%, var(--sd-text))`, 5.6:1 on a lit tab; the muted colour alone was 4.2:1 there. A draft badge inherits the tab's colour.
- Tree id labels: `color-mix(in srgb, var(--sd-accent) 55%, var(--sd-text))`, 5.0:1 on a selected row (was the accent at opacity .7, 2.5:1).
- Publish and every `.sd-icon-btn-primary`: the accent mixed 85% with `--sd-panel-border`, white label 5.0:1 (3.98:1 on the plain accent).
- Lock and link icons: the plain accent without opacity, 3.8:1. "No selection" (`.sd-breadcrumb-empty`) and a switched-off file's name: `--sd-text-muted`, 4.9:1; the "off" badge on `--sd-text`.
- Left panel small texts (#9099): the active palette tab and the Tree/Design sub-tabs mix the accent 55% with `--sd-text` (5.0:1), the underline keeps the plain accent; the System tab is `#818cf8` by `[data-tab="system"]`, no inline colour (5.1:1); the EXT badge `#79b8ff` on its blue tint; the empty Fonts note and the rule search placeholder on `--sd-text-muted`.
- `tests/designer_chrome_contrast_test.php` checks these and refuses an opacity coming back.
