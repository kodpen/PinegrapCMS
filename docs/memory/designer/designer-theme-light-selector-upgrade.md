---
name: designer-theme-light-selector-upgrade
description: Upgrade step 11.0.32 splits the Themes panel's old combined light block in designs, page copies, templates and old theme files with a PHP twin of the editor's split.
---

## What broke on a design nobody opened?
- A design saved before #1298 keeps `:root, [data-bs-theme="light"]` in its User Styles. That `:root` matches `<html data-bs-theme="dark">`, and `<style id="pg-user-styles">` comes after Bootstrap's dark block: on Dark the page kept its light Background rgb(253,246,227) with grey text. Only an editor open and save split it.

## What does step 11.0.32 do?
- `upgrade_2026_11_0_theme_light_selector()` in `includes/migrations/2026.11.0.php`, data only, label 11.0.32.
- The editor's split, on:
  - `style.style_custom_css`, each `meta: 'user-styles'` entry;
  - `page.page_custom_css`, the pre-2026.4.4 copy rendered while the design's own list is empty;
  - `design_template.template_json` `assets.css`, which new designs are installed from;
  - design CSS files (`files.design = 1`) in exactly the shape the old "Save these colours as a theme" wrote (`pg_theme_vars_is_old_theme_file()`), rewritten as the button writes now, `files.size` updated. It loads after User Styles and broke Dark on its own; the editor never touches it.
- Left as they are: text that is no JSON list, a list without User Styles, text without the old block, a stylesheet changed by hand, palette files (their unquoted `[data-bs-theme=light]` is not matched, as in the editor).
- One row at a time in id order. Past the pass budget it writes the last id and counts per column to `data/temp/upgrade_theme_light_selector.json` and calls `install_pause()`; the next pass goes on after that id (a new request from the file, when under 10 minutes old). Deleted when the step ends. Without the file it starts over: finished rows are read, not written.
- One `install_ran()` note with counts, `install_skipped()` when nothing changed.

## Why was the dark block found again before the insert?
- An old block after the dark block with nothing to move (fonts, radii) is cut out, which tidies the text before the dark block; the shared block then went in at the dark block's old offset and cut its selector in two (`[d:root, …` / `ata-bs-theme="dark"]`): font, radius and the design's dark values were lost. The editor did the same since #1298. Both twins find the dark block again; tests check the expected text, not only parity.

## Why is the PHP text the editor's, byte for byte?
- `includes/fn/designer_theme_vars.php` mirrors `_findRuleBlock()`, `_themeSelectorRe()`, `_themeFindBlock()`, `_themeCanonicalSelector()`, `_themeDarkOwnVars()`, `_parseThemeDecls()`, `_themeSetDecl()`, `_themeUpgradeLightSelector()` and `_sdThemeCssFromUserStyles()`.
- Traps: PCRE's `\s` and `trim()` differ from JavaScript's (U+00A0, U+FEFF, U+0085; an explicit class is used); `$` matches before a final newline (`\z`); `'0'` is a value; offsets are bytes, found again after every change.
- JSON is decoded as objects (`{}` stays `{}`) and written as `JSON.stringify()` writes it (no escaped `/` or Unicode, raw U+2028); a template document with its saver's flags.
- `tests/designer_theme_vars_test.php` runs the editor's helpers under Node and the twin over the same inputs (split, second pass, theme file, asset list, template). Change both twins together.

## What was checked on a copy of the sandbox?
- Published designs (old block before and after the dark block, fonts-only block after a dark block with blank lines, an old theme file), logged out at 1280 and 375, light and dark OS: Dark rgb(253,246,227) before, rgb(33,37,41) after; Light, Georgia, radius 0 and a design's own dark values kept. A second run changed no byte; an editor save wrote the same JSON.

## What is left open?
- Shared with the editor, both twins to change together: an old block inside `/* */` is split too; a `}` inside quotes counts as a brace; `_themeSetDecl()` appends after a hand-edited last declaration with no `;` without adding one.
