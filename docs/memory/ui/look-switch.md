---
name: look-switch
description: The look's switch (#9197): .pg-switch and the settings card and report drawer .switch, one drawing: white 18 px thumb, accent gradient, focus ring, disabled; the missing thumb
---

## Which switches are look switches?
- `.pg-switch` on a native checkbox (the recipe, `look-type-and-recipes.md`), and Bootstrap's `.switch` inside `.pg-set-card` (Site Settings) or `#report_settings` (the report settings drawer). One rule list in `backend.src.css`, beside `.pg-switch`; the drawer has no copy of its own since #9197.
- Every other Bootstrap `.switch` keeps the shell's thumb (`bootstrap-6-sibling-toggles-fullscreen.md`).

## How is it drawn?
- Track 38 × 22, grey (`--pg-fg-3` at 40%) with an inset shadow when off, the accent gradient (`--pg-accent-1` to `--pg-accent-2`) when on. Thumb: a white 18 px circle with `0 1px 3px rgba(0, 0, 0, 0.35)`, left when off, right when on, in both themes.
- `--bs-switch-border-width` is `0px`: a bare 0 made the thumb's size invalid and it vanished (#8981).
- The shell's dark `--pg-on-accent` checked thumb and blue focus knob skip look switches: `.switch:not(:where(.pg-set-card *, #report_settings *))` (#9197). The look's white `background-color` covered them already; the selector keeps them off for good.

## Focus and disabled?
- Keyboard focus (`input:focus-visible`): the look's ring `0 0 0 4px` accent at 24% beside the track's inset shadow. Bootstrap's outline and the panel's square `input:focus-visible` outline are off; a switch with `.is-invalid`/`.is-valid` keeps its validation ring.
- Disabled, on or off: the whole switch at opacity 0.5, the thumb white at full opacity and the default cursor. Bootstrap faded only the thumb (0.4, grey on dark) or the switch (0.65 when on). `.pg-switch:disabled` the same.
- `tests/look_switch_test.php`, `tests/bs6_switch_thumb_test.php`; the selector change is pinned in `tools/bs6_switch_texts.txt`.

## Why did the owner see a switch without a thumb? (#9070)
- Most likely the site did not have #8981 yet: from the settings cards (#8952) until #8981 (2026-11-12 23:20, the owner wrote at 23:55) the bare `0` border width hid the thumb, so the switch read as a coloured box. On development on 2026-11-13 all 79 settings switches had the white thumb in both themes, measured before #9197.

Related: docs/memory/ui/look-settings-screens.md, docs/memory/workflow/decisions-made-panel-8.md
