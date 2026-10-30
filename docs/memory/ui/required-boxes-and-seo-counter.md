---
name: required-boxes-and-seo-counter
description: Boxes the server refuses blank carry required (Delete in the same form gets formnovalidate); the SEO character counter is the field's description and says count, target and state in words
---

## Which boxes carry required although the save checks them on the server?
- Every box `validate_required_field()` refuses blank carries `'required'=>'required'`, so `pgInjectAriaAndRequiredMarkers()` marks its label (`panel-accessible-names.md`). Added for Order Report Name, Visitor Report Name, theme file Name, Edit Comment's Comment and Find & Replace's Find; later for the shared create/edit forms of ads (Name, Ad Region), login regions (Name), verified shipping addresses (Address 1, City, Zip Code), containers (Name), ship date adjustments (Zip Code Prefix, Shipping Method, Adjustment days), campaign profiles (Name, Subject, From Name, From E-mail Address), calendar event locations, menu items, product attributes and Edit Ad Region (Name), auto dialogs (Name, URL) and key codes (Offer Code, Quantity; Code on the edit screen only, since a new one may be blank). Before, people learned it only from the error after Save.
- Left without required although the server checks them: a box that can be hidden when the form is saved. The container's Length/Width/Height sit in inch/cm groups swapped with `d-none`; the verified address State is a `data-pg-combobox` select; the campaign profile's calendar event, custom form, product, page and schedule rows are checked only for some choices and sit in collapse rows; the calendar event's reservation fields likewise.
- File pickers the server refuses empty carry required too: Add Theme File's Local CSS Theme File (also `accept=".css,text/css"`; the System branch's CSV picker and name stay optional in their disabled fieldset) and Import Tracking Numbers' CSV File. The theme pickers' labels point at their own pickers (`for="custom_css_file"`, `for="system_theme_csv_file"`); both had `for="name"`, the hidden System name box.
- The API application edit drawer's Name (`d_name`) has no required: it sits on the General tab and a hidden tab would block Save with no visible message. The update branch of `api_settings.php` trims the name and refuses a blank one with 'Application Name is a required field!' before writing; before, it saved a nameless application.
- A Delete submit button in the same form carries `formnovalidate`: the `data-confirm-content` handler lets it submit natively, and a blank required box would otherwise block the delete. Pinned for ads, login regions, verified addresses, containers, ship date adjustments, campaign profiles, calendars, calendar events and locations, currencies, menu items, product attributes, ad regions, auto dialogs, key codes and short links.

## How is the Form Editor box on Edit Submitted Form named?
- The liveform field has `id="form_editor_username"` and its `<label for>` points at it; before, the label had no `for` and the box was unnamed.

## What does a screen reader hear from the SEO character counter?
- `renderSeoCounter()` in `initSeoCounters()` (`backend.src.js`) appends the counter id to the input's `aria-describedby` (appended, never replaced) and hides the visible `45/60 ↑` and the bar with `aria-hidden`.
- A `visually-hidden` sentence says the count, the target range and the state: "No text yet. Aim for {var:1} to {var:2} characters." or "{var:1} characters, too short / good length / too long. Aim for {var:2} to {var:3}.". The keys sit in the panel translate map (`includes/fn/output.php`) and are read with `pgLang()`.
- Used by Edit Page, the product screen, Mass Edit, Site Settings > SEO and the designer's page settings; none needed its own edit.

Tests: `tests/required_markers_and_seo_counter_test.php`, `tests/required_boxes_forms_test.php`, `tests/upload_and_name_required_test.php`.
