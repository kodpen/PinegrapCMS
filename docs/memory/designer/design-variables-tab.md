---
name: design-variables-tab
description: The Variables tab of Settings, Theme (DV2): the part 'variables', the file design-variables-<style id>.css found by its name, the save, the canvas, Preview and site loading it after the palette
module: design_themes
---

## Where is the tab?
- Settings, Theme has two tabs at the top of `#sd-theme-picker`: "Look and colours" (the cards, as before) and "Variables". No bar button; the tab is drawn by the part `variables` (`assets/js/style_designer_variables.js`, `pg_designer_parts()`), loaded the first time the tab is shown.
- A fold per group (Colour, Type, Spacing, Shape, then "Your own" with "Add a colour" / "Add a size", at most 24), a row per variable (swatch or "Aa" sample, a light and a dark colour picker, or the value field), the contrast rows of text and muted text on the surface, and "Back to the look and palette".
- Each row ends with "Used in n places" (DV3, `design-variables-options.md`); the start questions are DV4 (`design-variables-questions.md`), Export and Import DV5 (`design-variables-files.md`).

## How does a change reach the canvas?
- The part asks `designer/variables_state` (`includes/panel/designer.php`, designers only, a clean sub-action of `page_cache.php`) with the model, look and palette; the first time with `style_id` only, and the server reads the stored file. It answers `pg_design_variables_state()`: the model made safe (rebased when it was made from another look or palette), the defaults, the stylesheet, the contrast rows and the tab's rows (`pg_design_variables_client_list()`).
- A colour answers as it moves, a text field when it is left; the request waits 300 ms of rest. The stylesheet is tried on as `_sdThemeTry.variables` and `_sdThemeAssets()` puts it last, as a `<style id="pg-theme-variables">`, on the canvas and in the Preview.

## What happens to a refused typed value or a used own variable?
- A field's value the model would refuse is marked red with the row's bounds as its title and not sent (`_sdVarsTypedOk()`, twin of `pg_design_variable_value()`); a bare 0 only where the row starts at 0 (PR #11770).
- "Remove" on an own colour or size that elements read asks first: those elements lose the value, as `var(--pg-var-c-…)` has no fallback. A curve's elements keep its numbers.
- An answer to an older change never replaces a newer one in `style_variables`.

## Where is it kept, without a column?
- One design CSS file per design, `design-variables-<style id>.css` (`pg_design_variables_file_name()`), with a `files` row (`design = 1`). Found by its name; a file of that name without the variables head (`pg_design_variables_head_ok()`, the first 32 bytes) is never loaded and is not overwritten: the save warns.
- The tab writes the model as JSON into the hidden input `style_variables` (in `_pgStyleJSON()`, so Save sees the change); empty means unchanged. The design save (`designer_screen.php`, after `save_system_style()`) calls `pg_design_variables_save()`: the posted model, or with none the stored one rebased onto the look and palette just saved. No file and nothing posted: nothing is written.

## Why is the model rebased?
- A value equal to the old choice's default takes the new choice's default (`pg_design_variables_rebase()`), so a brand colour never changed follows a new palette instead of overriding it. A changed look or palette in the editor asks the part to work the stylesheet out again (`_sdVarsFollowTheme()`) when the design has a file or the part has run.

## What loads on the site?
- `get_page_content.php` passes `pg_design_variables_url($style_id)` (a stat and 32 bytes, kept per request) to `pg_design_theme_links()`. With a file: bridge, base, look, palette, then `pg-theme-variables`; the file alone also brings `bridge.css` and `base.css`, whose defaults are plain Bootstrap. A design that never saved variables has no file and its page does not change by one byte.
- The canvas reads the saved file's address from `sdDesign.themes.variables`.

## What is not carried?
- Duplicating a design (`duplicate_style.php`) does not copy the file yet.
