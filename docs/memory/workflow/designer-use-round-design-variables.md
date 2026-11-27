---
name: designer-use-round-design-variables
description: Designer use round UR1 (#11835): design variables used in a browser as a first-time person; faults found, fixed and left
---

## What was used, and how?
- On a `setup_sandbox.sh` instance with Playwright: the New design window's start questions (Blank, Bootstrap 5), the editor with the answers, an Options colour bound and detached, Save, Settings, Theme, "Variables", "More": "Ask me again", "Export", "Import" (a good file, a non-JSON file).
- 1440 and 390 px, the panel light and dark (the editor is always dark), the questions and the variable list by keyboard alone; an empty design and the 37-page "Online Store" template.

## What was found and fixed in #11835?
- The start answers were lost: `designer_screen.php` never read `start_variables`, so the design opened with the defaults and the first save wrote nothing (a Figma import's variables too). Now `pg_design_variables_start_editor()` fills the hidden input and the try-on; test first.
- "Ask me again" said "could not be loaded": the part looked for `window.StyleDesigner`, which the core's top-level `const` never is. Test first.
- "More" stayed open on a click outside and on Escape.
- The variable list: Escape or a choice left the focus on the page; arrows did nothing; Enter in the search was blurred by the right panel's Enter guard.
- The questions by keyboard: every choice redrew the step and dropped the focus; ten swatches were ten tab stops. Now one stop per group, arrows choose, the focus stays.
- The question cards centred their content, so titles in a row stood at different heights; on a phone the step line wrapped with a dangling connector.
- The Variables tab against its drawing: folds with the native triangle, no icon tile, value line or chevron, no Light and Dark heads, several open at once; "Type" read "Türü" (now "Typography"); at 390 px "More" ran off the window; the menu rows had no icons.

## What was left, and why?
- Swatches are named by their hex code for a screen reader; names need words per colour (server data).
- Step 1's field shows `#2563eb` as a placeholder and the sample uses it while nothing is chosen.
- On a phone the New design window keeps its start list above the questions, and focus leaves the window after its last button: the window is UR2's.
- A template design's rows all say "Not used yet": starters read no variables yet (`designer/design-variables-starters.md`).
- A colour row has two pickers, not the drawing's hex fields; a larger change than a fix.
- Not checked: the autosave's "Load" bringing `style_variables` back, the published site after Publish.

## Which questions went to the architect?
- In the PR body under "Decision question": should "Ask me again" open with the design's current answers; should an imported colour without a dark value take its light one instead of keeping the design's dark (a teal brand with a rose dark was seen); should "I'll choose" with no colour picked block Next instead of counting as skipped.

## What could not be run?
- The Figma start (no token): it shares the fixed path. No AI action is part of this feature.
- Timing: the core gets only `_sdVarsMoreWatch()` (a few lines, run when "More" opens); the rest is in the parts `variables` and `questions`, loaded on first use.
