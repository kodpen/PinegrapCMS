---
name: design-variables-questions
description: The design variables' five start questions (DV4, #11593): one part for the New design window and the Variables tab's "Ask me again", the answers, the model they give, how a new design carries them
module: design_variables
---

## Where are the questions?
- One plain file, `assets/js/designer_questions.js` (`window.pgStartQuestions.mount(host)`), with its own few CSS rules injected once. It also registers the editor part `questions` (bundle `style_designer_questions.min.js`, `pg_designer_parts()`).
- The words and choices come from the server: `pg_design_variables_questions()` (`includes/fn/design_variables.php`), asked once through `designer/variables_state` with `questions: 1`. Nothing is printed with the home page or the editor before the first use.
- Five steps: brand colour, second colour ("Choose for me" by default), feel (the built-in looks, six shown, "Show all 14 looks"), type feel (Clean, Classic, Friendly, Technical), roomy or compact. Step labels are "Fonts" and "Space", not "Type" and "Room": those keys already have other Turkish meanings.

## How does the New design window ask them?
- `designer_home.js`: the filled button of a start whose checked framework radio is `bootstrap5` (Blank, ZIP file, Pasted markup) loads the file and the data, hides the pane's form, Cancel and the button, and draws the questions with Back, Skip, Next ("Create design" on the fifth) in the foot. Back on question 1, choosing another start or closing the window restores the form. A Custom design or a template is not asked.
- The answers ride on the address as `&answers=<JSON>`, only when one was answered. `add_system_style.php` calls `pg_design_variables_start()`: the feel becomes the look; any other answer gives a model and its stylesheet, put in the hidden `style_variables` input (the first save writes the file) and tried on the canvas at once (`sdDesign.themes.variablesTry`, taken once by `_sdThemeAssets()`).

## How does "Ask me again" work?
- The Variables tab row has a quiet "More" (`<details>`), its one row "Ask me again" (`_sdVarsAskAgain()` in the core). The part draws in the tab's pane with Cancel, Skip and Next ("Use these answers"); no filled button.
- The answers go to `variables_state` with `answers` and the look the feel chose; the model is set before the look changes (`_sdThemeChoose()`), so the tab's own follow-up reads the new model. Cancel redraws the folds unchanged.

## What do the answers change?
- `pg_design_variables_from_answers($model, $answers, $defaults)`: the four groups go back to the defaults, then brand (light = dark), second colour (picked, or `pg_design_variables_companion()`: hue +40 degrees, saturation 35-85 %, lightness 30-55 %), the type feel's fonts, heading weight and base size, the room's base unit and section rhythm. Own variables stay. Shape follows the look.
- A skipped answer is `''`; skipping all five gives no file and the design opens as before. Answers are checked by `pg_design_variables_answers()`.

## What did it cost?
- Core `style_designer.min.js` +802 bytes (+242 gzip); the part 11,385 bytes (4,269 gzip), loaded on first use. Tests: `tests/design_variables_questions_test.php`.
