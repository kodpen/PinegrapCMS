---
name: duplicate-products-options
description: Duplicate Products (duplicate_products.php) copy options as a titled card of labelled form-field boxes above the list (step B11, #7552)
---

## How are the copy options laid out?
- One card, `Duplicate Options`, above the list: the sentence on how the new letter replaces the removed one, then a `row g-5` of `form-field` boxes, `col-12 md:col-4` each: Number of Letters to Remove, Word or Letter to Add and, only on a site with attribute options, Product Attribute.
- Each caption is `<label class="form-label" for>`; the letter count's help text is a `form-text` (`#lettertodelete_help`) the field names with `aria-describedby`.
- The list table below is unchanged (tables stay, `edit-screen-wide-layout.md`); the screen has no status column because it is a list with options, not an edit screen.

## What did it replace?
- A bare row of unstyled captions, unboxed `form-control` fields and a loose `small` span for the help text, left over from before the panel moved to Bootstrap 6.
- Pinned by `tests/field_caption_links_test.php` (`test_field_caption_duplicate_products_options`).
