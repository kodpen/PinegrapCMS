---
name: legacy-design-framework
description: The legacy framework key of a classic style moved into the editor (LG3): registry entry and flags, who writes it, the 11.0.187 data step, the Legacy / Eski badge on Visual Designs
---

## What is the legacy key?
- A third entry in `pg_design_frameworks()` (`includes/fn/designer.php`): label `lang('Legacy')` (Turkish "Eski"), no CSS or JS address, `bootstrap => false`, `legacy => true`. The other two entries carry `legacy => false`.
- It loads like `custom`: no framework files, no Bootstrap palette, look or palette. The style's own CSS rows and its frame component carry the look.
- `style.style_framework` (VARCHAR(20)) holds it: no schema change.

## How does code ask about a framework?
- By its flags, never its key text: `pg_design_framework_has($key, 'bootstrap')` / `($key, 'legacy')`. An unknown key reads as Bootstrap 5, as `pg_design_framework_key()` does.
- Moved to the flags in LG3: `pg_design_ai_framework_line()` and `pg_design_ai_known_class()` (`includes/designer_ai.php`, no Bootstrap), the template drawings of the designs home and the template modal (`includes/designer_screen.php`), `pg_legacy_design_from_classic()` (`includes/fn/legacy_design_theme.php`).
- `pg_design_ai_known_class()` still picks the Bootstrap 5 vocabulary file by `=== 'bootstrap5'`: a later Bootstrap key brings its own file.
- JS reads `sdDesign.frameworkInfo` flags only (`_sdFrameworkInfo()`, `_sdUsesBootstrap()`).

## Who writes it?
- `edit_system_style.php?classic=1` opens the style with framework `legacy`; the editor posts it and Save writes it (`pg_designer_screen_post()` → `pg_classic_style_mark_design()`).
- The bulk move (`includes/fn/legacy_to_designer_run.php`) passes `legacy` to `pg_classic_style_mark_design()`.
- A copy (`duplicate_style.php`) and a detached page keep the source's key, so a copy of a moved style is legacy too and loads the same theme.
- Never a blank design: `add_system_style.php` turns `?framework=legacy` into `bootstrap5`; the new-design question offers only Bootstrap 5 and Custom.

## What did the 11.0.187 step do?
- `upgrade_2026_11_0_legacy_design_key()` (2026.11.0, data only): `style_framework = 'legacy'` for a `custom` visual design (`style_layout = 'visual_designer'`) that has a shared component of category `classic_style:<its style_id>`. Re-runnable: a marked row is no longer `custom`.
- A design made custom in the editor has no such component and stays `custom`. A copy of a moved style made before the step has no component of its own id and stays `custom`.
- Before LG3 the move wrote `custom`; LG1 recognised a moved design by `custom` plus that component. The move itself is 2026.11.0 work (2026.4.10 has no `?classic=1`), so the step matters for sites that ran development, such as dev.pinegrap.com.

## Why does recognition read the flag alone?
- No query per page render, and a copy keeps the look of its source. The group design of `plans/legacy-group-design.md` (LG5) is a new style row holding the frames of several origin styles, none named after its own id, so a component check would miss it.

## Where is the badge?
- The Visual Designs screen (`view_system_styles.php`): `pg_designer_list_designs()` sets `framework_legacy` per row; `pg_designer_home_design_card()` prints `<span class="badge theme-secondary sdh-legacy">Legacy</span>` inside `h3.sdh-name`, after the name link, on the tile and the featured card. The featured card also lists "Framework: Legacy".
- `.sdh-legacy` (`assets/css/designer_home.css`): .75rem, weight 500, `nowrap`, so it wraps with the name's last word at phone width.
- Decision: badge text "Legacy" / "Eski" (`workflow/decisions-made-product-43.md`).

Related: docs/memory/designer/design-framework.md, docs/memory/designer/legacy-design-theme.md
