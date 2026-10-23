---
name: designer-theme-light-selector-upgrade
description: Upgrade step 11.0.31 splits the Themes panel's old combined light block in designs, page copies, templates and old theme files with a PHP twin of the editor's split.
---

## What broke on a design nobody opened?
- A design saved before #1298 keeps `:root, [data-bs-theme="light"]` in its User Styles. That `:root` matches `<html data-bs-theme="dark">`, and `<style id="pg-user-styles">` (printed per request by `get_page_content.php`) comes after Bootstrap's dark block: with a theme switcher on Dark, or Auto on a dark OS, the page kept its light Background rgb(253,246,227) with grey text. The editor split it only on open and save.

## What does step 11.0.31 do?
- `upgrade_2026_11_0_theme_light_selector()` in `includes/migrations/2026.11.0.php`, data only. Label 11.0.31: development ended at 11.0.30, open PRs held 11.0.26-11.0.29.
- The editor's split, on:
  - `style.style_custom_css`, each `meta: 'user-styles'` entry;
  - `page.page_custom_css`, the pre-2026.4.4 copy rendered while the design's own list is empty;
  - `design_template.template_json` `assets.css`, which new designs are installed from;
  - design CSS files (`files.design = 1`) in exactly the shape the old "Save these colours as a theme" wrote (`pg_theme_vars_is_old_theme_file()`), rewritten as the button writes now (`pg_theme_vars_theme_css()`), `files.size` updated. A theme file loads after User Styles and broke Dark on its own; the editor never touches it.
- Left as they are: text that is no JSON list, a list without User Styles, text without the old block, a stylesheet changed by hand, palette files (their unquoted `[data-bs-theme=light]` is not matched, as in the editor).
- One row at a time in id order; past the pass budget it calls `install_pause()` and the next pass of the request goes on after the last id. A new request starts over; finished rows are read, not written.
- One `install_ran()` note with counts, `install_skipped()` when nothing changed.

## Why is the PHP text the editor's, byte for byte?
- `includes/fn/designer_theme_vars.php` mirrors `_findRuleBlock()`, `_themeSelectorRe()`, `_themeFindBlock()`, `_themeCanonicalSelector()`, `_themeDarkOwnVars()`, `_parseThemeDecls()`, `_themeSetDecl()`, `_themeUpgradeLightSelector()` and `_sdThemeCssFromUserStyles()`.
- Traps: PCRE's `\s` and `trim()` miss the no-break and other spaces JavaScript takes (an explicit class is used); `$` matches before a final newline (`\z`); `'0'` is a value; offsets are bytes, only compared and cut at.
- JSON is decoded as objects (`{}` stays `{}`) and written as `JSON.stringify()` writes it (no escaped `/` or Unicode, raw U+2028); a template document with its saver's flags.
- `tests/designer_theme_vars_test.php` runs the editor's helpers under Node and the twin over the same inputs (split, second pass, theme file, asset list, template). Change both twins together.

## What was checked on a copy of the sandbox?
- Three published designs (old block before and after the dark block; an old theme file), logged out at 1280 and 375, light and dark OS: Dark rgb(253,246,227) before, rgb(33,37,41) with rgb(222,226,230) text after; Light #fdf6e3, Georgia and radius 0 kept. A second run changed no byte; the editor found nothing to split and its save wrote the same JSON.

## What is left open?
- A template document imported from another site later is not split until its design is saved in the editor.
