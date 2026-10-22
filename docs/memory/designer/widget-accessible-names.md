---
name: widget-accessible-names
description: Names and keyboard use in system widget output: CAPTCHA answer id and label, no empty liveform ids, product carousel link names, the login region's account toggle as a button.
---

## Why does a liveform field without an id print none?
- `liveform::output_field()` wrote `id=""` on every field given no id. The CAPTCHA block alone printed four (honeypot, two hidden fields, the answer), Chrome reported a duplicate id and no label could point at the answer. A field given no id now prints no id attribute; a given id prints as before. Test: `tests/liveform_test.php`.

## How is the CAPTCHA answer labelled?
- `get_captcha_fields()` (`includes/fn/auth.php`, used by the classic screens and by the widgets' `captcha` section) gives the answer box the id `captcha_submitted_answer` (the classic themes style `input#captcha_submitted_answer`; a second CAPTCHA in the same request is numbered, `pg_captcha_answer_id()`) and writes the question as `<label class="software_captcha_question" for=…>`. The honeypot stays hidden and unlabelled.

## What names the product carousel's links?
- The slide links and thumbnail links wrap pictures with `alt=""`, so they carry `aria-label` "Image N of M" (`pg_carousel_image_label()`, translated); the indicators use the same words instead of the untranslated "Slide N". The variant chooser rebuilds the gallery with the same labels (`imageLabel()` in `assets/js/pg_civ_variants.js`, words from the payload's `labels.image_of`); bump `$_civ_v_v` when that script changes.

## Why is the account toggle a button?
- The login region's signed-in toggle was `<a href="#" role="button">`: Enter opened the menu, Space did not. The starter (`case 'login_region'` in `style_designer.js`) and every design template's own layout write `<button type="button" class="btn btn-link … p-0 border-0 …">`; it looks as before. A layout saved earlier keeps its anchor until the designer replaces it.

Related: docs/memory/designer/account-widget-forms.md, docs/memory/designer/custom-form-options-and-rules.md
