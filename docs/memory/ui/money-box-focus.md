---
name: money-box-focus
description: A money box that opens focused is focused on document ready after the Inputmask binding, never by an inline script while the page parses (gift card Amount, #9047)
---

## How is a masked box focused when its screen opens?
- In a `$(function () { … })` ready handler, not an inline `focus()` that runs while the page is parsed. The panel loads `inputmask.binding.js` in the head (`includes/fn/output.php`), and its ready handler masks every `[data-inputmask-alias]` box; ready handlers run in the order they were added, so one added in the body runs after it.
- The handler masks the box itself first when the binding has not (`box.inputmask === undefined`, `Inputmask().mask(box)`), so a change of script order cannot bring the fault back. The binding skips a box that is already masked.
- Only Create Gift Card (`pg_gift_card_form_card()`, `includes/forms/gift_card_form.php`) opens with a masked box focused; no screen puts `autofocus` on one.

## What was the symptom? (#9047)
- The inline `$("#amount").focus()` ran before the mask was bound. The mask then attached to a focused box with the caret after the decimals, so digits typed without a click went into the kuruş: `50` became 0,50, `1.234,56` 0,56, and `pg_gift_card_form_check()` accepts any amount above 0.
- Checked in headless Chromium on a page with the panel's jQuery, Inputmask, the binding and the `currency` alias extension: `50`, `50,00`, `1.234,56` and `7` give 50,00 / 50,00 / 1.234,56 / 7,00, also with the binding missing or loaded after the box. `tests/gift_card_form_test.php` pins the script. Not checked on a running panel.

Related: docs/memory/ui/money-field-input-mask.md
