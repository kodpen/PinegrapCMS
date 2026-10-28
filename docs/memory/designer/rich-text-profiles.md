---
name: rich-text-profiles
description: pg_sanitize_rich_text() profiles: visitor (default, site output) drops class, layout styles and outside images and marks links ugc; staff keeps the shared allow-list; which callers pass staff.
module: editor
---

## Which profiles does `pg_sanitize_rich_text($content, $profile)` have?
- One allow-list (`includes/fn/editor.php`): script-like elements, event handlers, id/name/data-*, `javascript:`/`data:` addresses and CSS with `url()`/`expression` go in both.
- `'staff'`: that allow-list, unchanged (class, any safe style, rel, outside images stay).
- `'visitor'` (the default; an unknown name is visitor too) adds:
  - `class` removed (no Bootstrap `fixed-top`, `position-fixed` overlays).
  - `style` rebuilt from text formatting only (`_pg_rich_text_visitor_style()`): colour, background colour, font, text align/decoration/transform/indent, line height, vertical align, left/right margin and padding (editor indent), list style, direction, border. Position, z-index, display, width/height and the rest drop; so do negative lengths and viewport units (vw, vh, vmin, vmax). No declaration left: no attribute.
  - every link gets `rel="nofollow ugc noopener"`, replacing its own `rel`.
  - an `img` is removed unless `_pg_rich_text_image_is_local()`: relative, `{path}`, or http(s) on `HOSTNAME_SETTING` (never the request Host). Protocol-relative (`//`, `/\`), user information and other schemes are outside; an empty `src` too.
- Decided by the owner on 2026-10-28 (plan `security-findings-2026-10` item 8): visitor HTML could restyle or cover the page and call outside servers.

## Who passes which profile?
- Staff: `edit_field.php` and `view_fields.php` (information text), `includes/api/resources/calendars.php` (API event description), `edit_submitted_form.php` (below).
- Everything printed on the site or stored from a visitor keeps the default: designed and classic forms, cart/express order fields, form item/list views, confirmation screen, widget tokens, signatures.
- A new panel screen that prints staff-written HTML passes `'staff'`; a site screen never does.

## What does the submitted form editor do with a stored value?
- `edit_submitted_form.php` runs a rich text area's stored string through the staff profile before `prepare_rich_text_editor_content_for_output()`. It covers `standard` rows that were never filtered: answers saved before the field became rich text, and API writes. Symptom fixed: such a row loaded raw into the staff editor, which parses it as HTML.
- The staff save path (`includes/forms/submitted_form_form.php`) is unchanged.

## Tests?
- `tests/rich_text_profiles_test.php`; `custom_form_rich_text_test.php` covers the shared allow-list.
