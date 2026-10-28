---
name: form-adorn-settings-cards
description: Form-adorn on the Site Settings cards (B9f part 6, #2501): which fields, the row rules that name the wrapper, the add-on type that keeps the rows 38px tall, what stays an input-group
---

## Which settings fields are form-adorn?
- Every single add-on field in `includes/settings/` api, appearance, contact, features, firewall, prep, security and seo (30 fields, #2501), with `form-adorn-end` and the unit in the add-on. Each keeps its `<label for>` above the wrapper; the tracking code duration label pointed at a wrong id and now names its input.
- The toolbar search of `screen.php` (`#pg_settings_filter`): a small `rounded-pill` wrapper with `pg-toolbar-search`, the input named by `aria-label`, as on the other toolbar searches (#2478).
- `commerce.php` (part 9): gift card validity, reward goal and membership length, surcharge, affiliate default commission rate. The surcharge add-on was a `<label for>` naming the affiliate rate and the installment label named the secret key; both now name their own field.

## What stays an input-group?
- The language address prefix (two add-ons), the membership warning days (`title` hint on the add-on), the SEO groups and the Paraşüt authorization code with buttons. `tests/bs6_form_adorn_test.php` counts the groups left per file.

## How does the card CSS hold the wrapper?
- The `.pg-set-card` row shape test (`:has(> .form-label):has(...)`) and the control placement (`grid-area: pgctrl`) in backend.src.css list `> .form-adorn` beside `> .input-group`; without it a converted cell fell out of the two-column row.
- The `pg-f-xs/sm/md/lg` caps need no line of their own: the wrapper is a `.form-control`.

## Why does the add-on keep a larger type?
- An input-group was 38px tall because its add-on used the body size (16px / 24px), while a plain settings field is 33.5px (13px). Drawn at the field's size, every converted row lost 4-5px.
- `.pg-set-card .form-adorn > .form-adorn-text` takes `--bs-btn-input-font-size` and `--bs-btn-input-line-height`, and the wrapper aligns on the baseline so value and unit share one line. Measured before and after (1440 and 390px): width, height, right edge, label offset and row height of every field unchanged.

Related: docs/memory/ui/form-adorn.md, docs/memory/ui/settings-card-row-layout.md, docs/memory/ui/settings-card-field-widths.md
