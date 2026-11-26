---
name: sketch-build-starters
description: How "Build the page" gets the palette starters (#11794): the host's maker, block styles after the build, h1 not badge, rhythm only on py-5, pages built before the fix
module: designer
---

## How does the builder get a starter?
- `_sdSketchBuildHost().make(type)` asks `createFromPalette('component', {componentType: type})`, as the palette does, built dry (`_sdPaletteDry`).
- Until #11794 it asked `createFromPalette(type)`, which has no case for a starter's name: every block came back null and every section was built as a heading and a paragraph.

## When do the starters' block styles join the design?
- Only after the page is added (`addPage`) or built again (`setChildren`): the part `sketchbuild` collects the types made in `parts()` (`list.blocks`) and calls `host.blockStyles()`, which runs `_sdEnsureBlockStyles(_SD_BLOCK_CSS_NEEDS[type])`.
- A refused or failed build adds nothing to the design (no page, no User Styles rule).

## What does the builder change in a real starter?
- The title (typed word, page title, kind's name) goes into the first h1-h6 (`titleHeading()`): the heroes' "New!" badge and the image cards' duration badge are headings drawn as `<span>` and keep their word. A copied column numbers its h1-h6 too.
- The section rhythm goes only on a root whose class has `py-5` and with no `style` in `_attrs` (`takesRhythm()`): `hero-image-bg` keeps its background picture in `_attrs` style, and `_attrs` prints only for an empty prop, so an inline style would hide it.
- Checked once against the real trees (in Node): the description goes into the hero's paragraph; features-grid, the cards and image-grid have one item row the column count fits; features-alt's first row is text and picture, left at two; `_sketchId` is on every root.

## What happens to a page built before the fix?
- Its sections are text sections with a `_sketchId`. "Build again" keeps a section whose boxes did not change, so it stays text; a section whose boxes changed and was not edited is built anew from its starter (`sketch-build-again.md`).

## Where is it tested?
- `tests/sketch_builder_test.php`: stubs shaped like the heroes and image cards; and a read of the source that fails when the host's call or a starter name drifts: the maker asks `'component'`, and every name `blockFor()` answers is a `ctype === '<name>'` branch of `createFromPalette()`, a block family's item or a `COMPONENTS` entry.
