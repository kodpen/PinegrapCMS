---
name: page-style-screen-guards
description: Page style screens: which styles the custom edit screen opens, where View Source returns, how bulk delete reads its selection
---

## Which styles does the edit screen open?
- Only custom ones (`pg_style_edit_screen()`): a system style or a visual design opened by URL is sent to `edit_system_style.php?id=`, and a post for one is refused as not found; the form had written custom values over a design's code and layout, and Duplicate copied all its pages.

## Where does View Source go back to?
- `view_system_style_source.php` without `send_to`: Save & Return and Cancel go to `edit_system_style.php?id=` (the notice waits there); `pg_safe_redirect_path('')` was the site's home page and `view_styles.php` lists no designs. The style name is printed above the last-modified line and in the title.
- `delete_styles.php` reads `styles` only as a list; a post without one gets the "select at least one" error without a PHP warning.
