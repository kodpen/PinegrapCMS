---
name: designer-block-variant-picker
description: Variant Picker block (variant-picker, Marketing): radios and :has() switch a picture, colour dots or text pills, two to six choices, its Options panel, Product Launch's colour band
---

## What is the Variant Picker?
- `variant-picker` (PR #8781, B4 of [[designer-bold-pages-steps]]), palette > Marketing, in `_noWrapCtypes`, needs the core rule `pg-variant`. It replaced `launch-colours` of the Product Launch family; no copy of that block is left.
- A section `pg-variant pg-variant-dots` (or `pg-variant-pills`): heading, lead, a picture stage, then a fieldset (hidden legend "Colour") holding the choices. Choice n is `pg-variant-choice` with the radio `btn-check pg-variant-pick pg-variant-pick-n` and its `label.pg-variant-label` (`for` = the radio's id); picture n is `pg-variant-shot pg-variant-shot-n`. One radio group per picker (`kit.uid('vp')`), the first checked.

## How does it switch without script?
- `.pg-variant:has(.pg-variant-pick-n:checked) .pg-variant-shot-n { display: block; }` for n 1 to 6; every other picture is `display: none`, so a screen reader meets only the shown one. A keyframe fades it in, none under reduced motion. Without `:has()` the first picture stays.
- `:has()` and descendant selectors only, and no grid stacking: on the canvas `.sd-wrap` wraps every node, so a child selector or a grid cell would miss. The label, dot and pictures carry no display utility (Bootstrap's are `!important` and would beat the rule).

## What are the two looks?
- Colour dots (`pg-variant-dots`): a round dot over each name. The block keeps the dot's colour in its style attribute, the Background control's store. The dot's default colour rule has one class only (`.pg-variant-dot`), so a design's colour class written after it wins.
- Text pills (`pg-variant-pills`): the dot is hidden and the label is a rounded pill, the checked one in the text colour (`--bs-emphasis-color`), so it follows light and dark.
- Focus shows a dashed outline on the dot or the pill; the radio itself is `btn-check` (visually hidden, still keyboard reachable: arrows move within the group).

## What does Variant Picker Options do?
- The Properties panel shows it on a node whose classes hold `pg-variant` (`propsVariantOptions()` in `propsSemantic`): Look (Colour dots / Text pills) and Choices (remove the last / count / add). Buttons are `data-vp-action`; a change takes the undo snapshot first.
- Add copies the last choice and the last picture with fresh node ids: renumbered, unchecked, id `<group>-<n>`, label `for`, name, layer name and picture alt "Choice n". The copy keeps the copied colour, picture and caption, to be replaced. At six it does nothing.
- Remove drops the last choice and its picture (`pg-variant-shot-n`); a checked one passes the check to the first. At two it does nothing.

## Known limits?
- A picker copied by copy and paste keeps its radio group name, so the two copies switch together until a group name is changed in the radio's attributes.
- On the canvas the hidden pictures are reached through Layers (or by checking their choice).

## Where is it used?
- Product Launch's colour band (`$launch_colours` in `pinegrap-launch/pages_public.php`, home and features): the same classes, the dots coloured by the template's `plx-swatch-<key>`, radios `lx-colour-<key>`, figures with captions as pictures; the template ships `variant-picker`'s rules in its User Styles ([[design-template-launch-home]]).

## How is it checked?
- `tests/designer_block_variant_picker_test.php` runs the builder and helpers under Node: classes, labels, one checked radio, every `pg-` class in the rule, add to six and remove to two, the look switch, a nested picker, the launch band. `tests/design_template_launch_assembly_test.php` checks the band's numbered radios and pictures.
