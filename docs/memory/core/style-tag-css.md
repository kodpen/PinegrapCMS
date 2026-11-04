---
name: style-tag-css
description: CSS printed inside a <style> element goes through pg_style_tag_css(), never h(); the theme designer preview in get_page.php (edit_theme) and its theme_id key
module: core
---

## How is CSS printed inside a `<style>` element?
- Through `pg_style_tag_css()` (`includes/fn/core.php`): the text stays as written, only a closing `</style` becomes `<\/style`, the one sequence that ends the element early.
- Never `h()`: a `<style>` element is raw text, entities are not decoded, so `'Open Sans'` became `&#039;Open Sans&#039;` and `ul > li` became `ul &gt; li`, both broken CSS.

## What did the theme designer preview do wrong?
- `get_page.php` with `edit_theme=true` (a Designer previewing a theme) printed the session's theme CSS through `h()`, so the preview dropped quoted fonts and child selectors that the saved file keeps.
- It read `$_SESSION['software']['theme_designer'][$_GET['theme_id']]` raw: a missing `theme_id` logged a warning, `theme_id[]=` stopped the page on PHP 8 (array as key). It now takes a scalar `theme_id` only and an empty code when the session has none.
- Test: `tests/theme_preview_style_tag_test.php`.
