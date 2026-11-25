---
name: theme-activation-page-styles
description: What turning a theme file on or off does to the pages' styles: preview_styles rows per theme and device type, written when it goes off (replaced or turned off alone), applied when it comes on
---

## Where does a theme's activation run?
- `pg_theme_file_form_save()` (`includes/forms/theme_file_form.php`), shared by Edit Theme File and the All Themes list's quick edit (Activated for Desktop / Activated for Mobile). One theme is activated per device type.

## What does turning a theme on or off write?
- `pg_theme_activation_changes()` (pure) gives, from the flags before and after, the device types it was turned off for (`keep`) and on for (`apply`).
- Turned off, whether another theme takes its place or it is set to No alone: `pg_theme_keep_page_styles()` replaces its `preview_styles` rows of that device type with the styles the pages wear now (`page_style` or `mobile_style_id`, pages with 0 get no row).
- Turned on: the pages move to the styles in its rows for that device type; a page without a row keeps what it wears.

## What was the fault before?
- Only a theme replaced by another got its rows rewritten. A theme set to No alone kept the rows of its last replacement, so turning it on again (No then Yes on the list) moved the pages back to those older styles, undoing every style chosen while it was on. Fixed in the site themes bug hunt (#11474).

## What is left as it was?
- A page with no style of its own (0) gets no row: after a round trip through another theme it keeps the style the other theme gave it. The preview screens read "no row" as "the activated style", so writing 0 rows would change what they show; left alone.

Related: docs/memory/ui/page-bulk-edit.md, docs/memory/workflow/bug-hunt-areas-site-themes-and-style-switch.md
