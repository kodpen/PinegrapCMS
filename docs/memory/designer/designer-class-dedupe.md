---
name: designer-class-dedupe
description: How the designer replaces a class of the same family when one is added (deduplicateCssClass, CLASS_MUTEX_PREFIXES): longest prefix, same breakpoint only, callers, test
---

## What happens when a class is added?
- `deduplicateCssClass(cssClass, newClass)` in `style_designer.js` drops the classes that share the new class's family before it is added. The family is the LONGEST entry of `CLASS_MUTEX_PREFIXES` the new class starts with (`p-sm-3` → `p-sm-`, `d-md-block` → `d-`).
- Only classes at the same breakpoint go: the breakpoint is the `sm|md|lg|xl|xxl-` infix right after the prefix. `d-md-block` beside `d-none` keeps it and replaces `d-md-flex`; `d-flex` replaces `d-none` and keeps `d-md-block`; `p-4` replaces `p-3` and keeps `p-md-5`.
- Before #1728 it removed every class of the short prefix, so typing `d-md-block` on a `d-none` element dropped `d-none` (the "hidden on mobile" pattern could not be built) and a responsive flex/justify class wiped the base one.

## Who calls it?
- The Attributes class input (`addClassToken()`) and the Properties panel's `button[data-util]` pickers. `select[data-util]` removes only its own exact prefix (already per breakpoint).

## What is left as is?
- `flex-` is one family, so `flex-column` still replaces `flex-wrap` (different properties); `text-` is not a family on purpose (alignment and colour coexist). The predictor's `slot()` ([[style-predictor]]) knows the real property sets if a later step wants them.

## How is it tested?
- `tests/designer_class_dedupe_test.php` cuts the prefix list and the function out of the script and runs them under Node.
