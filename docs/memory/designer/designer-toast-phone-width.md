---
name: designer-toast-phone-width
description: Visual designer toasts (sdToast()) on a phone-width editor span the screen with 12 px at each side so a long message wraps inside it (#10072)
---

# Designer toasts on a phone-width editor

## Where do the visual designer's toasts sit?

`sdToast()` (`assets/js/style_designer.src.js`) puts them in `.sd-toast-container`, drawn in the TOAST NOTIFICATIONS section of `assets/css/style_designer.css`: bottom right (`bottom: 24px; right: 24px`), newest at the bottom, a toast at most 400 px wide (`--bs-toast-max-width: 400px`).

## What changes below the small breakpoint?

Under `@media (max-width: 575.98px)`, next to the toast rules, the container spans the screen with `left: 12px; right: 12px; max-width: none`, and `.sd-toast` takes `--bs-toast-max-width: 100%; width: 100%`. A long toast wraps inside the screen. Wider screens are unchanged.

## What symptom did it fix?

At 390 px a long toast (for example "Convert to design elements" on a classic page's System region) was 400 px wide at `right: 24px`, so its start ran off the left edge and could not be read (#10072).

## Does the chat offset still apply?

Yes. `body.design:has(#pg-chat-root) .sd-toast-container { bottom: 106px; }` is more specific and still lifts the stack above the chat button at every width; the phone rule changes only the sides.

## Which test pins it?

`tests/bs6_designer_toast_test.php`, `test_bs6_designer_toast_phone_width()`: the narrow-screen rule and the unchanged wide-screen values.
