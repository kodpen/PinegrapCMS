---
name: booking-templates
description: The online booking page of the Salon, Clinic and Law templates (BK10): book_online with the booking widget's starter layout beside the request form, the links between them, new installs only
---

## What do the templates ship?
- Salon (1.2.0), Clinic (1.3.0) and Law (1.1.0) carry a public page `book_online` (name `lang('book-online')`, title "Book Online") placing widget `booking_slots`: `config` `regionType => booking`, `'tree' => 'starter'` (the editor's starter: service, staff, day and time, details, confirmation). Beside it: "Rather ask first?" (Law: "A new matter?") linking the request form, and the opening hours; the Clinic repeats its emergency note.
- The request form page (`appointment`, Law `consultation`) keeps its form and gains a "Pick a free time now" box linking `{{page:book_online}}`.

## Why beside the form and not instead of it?
- The plan's rule: installed templates keep working and switching changes no installed site. A template is read only when a site opens it, so the change reaches new designs only; the request-and-confirm flow, its records, My Appointments and the staff lists stay for visits that need a word first (a first colour, an unknown doctor, a Law conflict check).
- A new install has no service yet, so the widget shows its `no_services` text until Settings › Bookings has one ([[booking-widget]]); the page needs no shop.

## Why the starter layout and no PHP tree?
- One layout, the editor's `_buildStarterTree()` case `booking`, mirrored by `_render_system_widget_booking()`; a third PHP copy would drift (`system-widget-change-checklist.md`). The cost: a starter widget keeps its page out of the installer preview ([[design-templates-file-format]]).

## Where does the consent box link?
- To the page named `lang('privacy-policy')`, which all three templates ship (`privacy`), so `has_privacy_page` holds ([[booking-widget-confirm]]).

## Which tests cover it?
- `tests/booking_templates_test.php`: each template's booking page is public, places one `booking` widget with the starter tree on its own page, the two pages link each other, a privacy page of that name exists. The Salon and Law page lists and the Clinic version pins were updated.
