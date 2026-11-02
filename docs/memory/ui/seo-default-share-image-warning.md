---
name: seo-default-share-image-warning
description: The SEO card's Default Share Image warning names each form once, told apart, without binned forms, five names then a count
module: seo
---

## What does the warning list?
- The forms whose record pages share the default share image (`pg_record_forms_sharing_default_image()`, `includes/fn/seo.php`; when a form shares it: `designer/record-media-field-share-image.md`).
- Forms whose page is in the Recycle Bin are left out: there is nothing to fix on them.
- Same-named forms are told apart as the custom form pickers do (`pg_submitted_form_list_labels()`, `form-names-distinct.md`): ` — <page name>`; a label still equal gets ` (#id)`.
- `pg_record_forms_sharing_default_image_text()` writes the first five labels and "and N more", so the warning stays short (`includes/settings/prep.php`).

## Why?
- It listed one bare form name per form page: on a site with several design templates, 38 names with 14 distinct, "İletişim Formu" nine times, binned forms among them (#5026).

## Tests?
- `tests/seo_default_image_forms_test.php`.
