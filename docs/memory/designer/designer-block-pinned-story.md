---
name: designer-block-pinned-story
description: Pinned Story block (Scroll Scenes): a tall section whose sticky stage shows its layers one at a time on scroll; scene interaction, pg-scene rule, fallback, tests.
---

## What does Pinned Story draw?
- Palette > Blocks > Scroll Scenes > Pinned Story (`scene-pinned-story`, B7 of plan designer-bold-pages), in its own family file `assets/js/style_designer_blocks_scene.js` (prefix `scene-`, group "Scroll Scenes").
- `section.pg-scene.bg-body` with `_fx {effect: 'scene'}` > `div.pg-scene-stage` > three layers. Each layer is a `div` with the attribute `data-pg-scene-layer`, a container and a row: the number (`01`), an `h2` (`display-5`), a lead line and a picture (`pg-scene-shot`), picture first on a phone.

## How does the scene play?
- `pg_fx.src.js`: a `scene` node with two or more `[data-pg-scene-layer]` elements gets `--pg-scene-steps` (the layer count) inline; each frame `pgFx.sceneStep()` cuts the scroll through the section (top at the window's top to bottom at the window's bottom) into equal parts and writes the step as `data-pg-scene-step`, and `data-pg-scene-on` on that step's layer alone.
- The `pg-scene` rule acts only on `.pg-scene[data-pg-scene-step]`: the section is one window (`100svh`) tall per layer, the stage is sticky at the top with a one-cell grid holding every layer, unmarked layers are hidden (opacity and visibility, .6 s), the picture is capped at 45svh so text and picture fit a phone's window.
- Layers are found by the data attribute, not a class, so a layer the operator duplicates is a step too.

## What happens without the script?
- No script, reduced motion (also turned on while open: the step and marks are removed), the editor's canvas (`pgFxEditing`, so each layer can be edited) and a scene with one layer: no step is written, and the layers stand one under the other as plain `py-5` bands, each heading in order.
- Hidden layers are `visibility: hidden`, so a screen reader reads the step on screen; the stacked fallback reads them all.

## What to watch for?
- Sticky holds only when no element around the section has `overflow: hidden`, `auto` or `scroll`.
- The Interactions panel keeps "Pinned scene" in the effect list only on a node that has it, with a note; it has no field and no Play button.

## How is it tested?
- `tests/designer_blocks_scene_test.php`: the rule (only under the step attribute, sticky, one window per layer, the marked layer alone) read by `_pg_designer_block_css_parse()`, and the block drawn under Node (group, section, stage, three marked layers, headings, pictures with alt text).
- `tests/designer_fx_test.php`: `test_designer_fx_scene`, `sceneStep` cases, the panel, and `test_designer_fx_script_scene` on a fake page.

Related: docs/memory/designer/designer-interactions.md, docs/memory/designer/designer-block-full-bleed-giant-type.md
