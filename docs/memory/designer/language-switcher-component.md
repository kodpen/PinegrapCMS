---
name: language-switcher-component
description: The Language Switcher Button component (COMPONENTS.language_switcher, 2026.4.7): a placeholder in the saved body, drawn per request after the language pass; the pattern for request-bound output.
---

## Why is it not drawn into the saved body?
- Its output depends on the request (language, address, query string), while a page body is stored once (`page.page_tree_code`). `_render_component_html()` writes a placeholder carrying its settings (`pg_language_switcher_placeholder()`: `<div hidden data-pg-language-switcher="{json}">`); `pg_tr_finalize()` draws it after the language pass (`pg_language_switcher_expand()`, `includes/fn/widgets_language.php`), so its names and addresses stay as drawn. With fewer than two languages it draws nothing.
- Read back, the placeholder's settings pass through `pg_language_switcher_options()` again: whatever a placeholder says, only known values are drawn.
- A new component whose output depends on the request uses the same pattern.

## How does the editor draw it?
- The site's languages come in `sdDesign.siteLanguages` (`pg_language_switcher_editor_languages()`, source language first); the canvas and `toHTML()` draw the same markup (`_sdLangSwitcherMarkup()`).
- The token-based language switcher system widget of 2026.4.6 (`language_switcher` in `SW_TYPES`) is a separate thing and stays.

Related: docs/memory/designer/palette-component-atomic.md
