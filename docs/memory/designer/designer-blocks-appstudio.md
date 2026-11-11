---
name: designer-blocks-appstudio
description: The App Studio block family (appstudio- prefix, own palette group): phone fan hero, app switch, release chapter, two platforms; why not launch-; rules, motion, test
---

## Where does it live?
- `assets/js/style_designer_blocks_appstudio.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `appstudio-`, palette group "App Studio" (`bi-app-indicator`). Built for the Mobile App Developer template's own look ([[design-template-appstudio]], `appstudio-1` of `plans/design-template-own-looks-order.md`); usable by any app maker or studio.

## Which blocks does it hold?
- `appstudio-hero` (h1): a dark band (`data-bs-theme="dark"`, `bg-body text-body`), eyebrow, a very large line, a lead, two buttons, fading in on load; under them `pg-appstudio-fan`, three `pg-appstudio-phone` frames (the middle `pg-appstudio-lead` larger) rising on load (stagger 150), cut by the band's edge over the `pg-appstudio-floor` wash.
- `appstudio-switch`: a `fieldset` of three radio inputs (`btn-check pg-appstudio-pick-N`, one name, a visually hidden `legend`) drawn as pill buttons; the checked one shows its phone screen and its words (`pg-appstudio-shows-N`, stacked in a `pg-appstudio-stage` grid cell) by CSS `:has()`, no script. Header rises, phone stage scales on view.
- `appstudio-release`: a dark chapter: eyebrow, the app and version very large (`pg-appstudio-figure`), the notes, `h3` What changed with three changes tagged New / Improved / Fixed rising (stagger 120); the phone beside it has parallax (speed 15).
- `appstudio-platforms`: header, then two `bg-body-tertiary` cards (Apple, Android) with their tools as badges and a phone peeking out of the card's bottom (`pg-appstudio-peek`); the row rises on view (stagger 150).

## Why not the Product Launch blocks?
- `launch-` sells one product on a launch day (countdown, specs, pre-order). An app studio shows many apps, their releases and the two platforms, so its scenes are the fan of several apps, a switch between apps, a release told as a changelog and native tools. The phone frame is drawn by CSS around a screenshot, not a product photo.
- The home's figures reuse the Creative group's `bento-features` structure (wide primary tile, stat, icon and dark tiles) with a view stagger on the row; no new bento block.

## What do its rules and motion follow?
- Rules `pg-appstudio-phone` (9:19.5, bezel `--bs-secondary-bg`, the device's own radius under every look), `-tight` (clamp 2.5–5.5rem, the look's heading tracking pulled in), `-fan`, `-switch`, `-figure`, `-peek`. Colours only `var(--bs-…)` / `var(--pg-…)`; descendant selectors so the canvas's `.sd-wrap` matches.
- Motion only from `props._fx` ([[designer-interactions]]) plus the switch's opacity fade, which `prefers-reduced-motion: reduce` turns off. Nothing plays by itself.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_appstudio_test.php` runs it under Node (`tests/helpers/designer_family_run.php`): own group, a section per block, one top heading and no skipped level, no fixed colour, `pma-` or `opacity-` class, `{var}` filled, no year or month, every interaction kept by the normalizer, three fan phones, three choices with a screen and words each, the release's tags, the tools; the fieldset, legend and `for` read from the file; the rules through `_pg_designer_block_css_parse()` with no colour of their own.

Related: docs/memory/designer/designer-blocks-overview.md, docs/memory/designer/designer-block-motion.md
