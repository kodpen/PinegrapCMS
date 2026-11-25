---
name: accessibility-reader
description: The Accessibility view's pure reader (PGA11yReader, AV1 #11529): tab order, heading outline, landmarks, picture texts, control names over an element tree, faults only with the audit's codes
---

## What is it?
- `window.PGA11yReader` in `assets/js/pg_a11y_reader.js` (#11529), pure, tested in Node by `tests/a11y_reader_test.php`. The reader of the Accessibility view (`plans/designer-accessibility-view.md`); it draws nothing.
- The first source of the part `accessview` (AV2 #PR, `accessibility-view.md`): not in the core bundle, so the core opens no slower.

## What does it read?
- A tree of elements, not of designer nodes: `{tag, attrs, children, node, hidden, ref}`, text as `{text}` or a string. It reads what the canvas drew, so widgets, shared components and custom HTML count as the browser sees them.
- `fromDom(el, {hidden, skip})` builds it from the canvas: `node` from `data-sd-id`, `ref` the element; `hidden(el)` is the caller's computed display/visibility, `skip(el)` leaves out the editor's chrome. An element without a node reports the node it sits in.

## What does it answer?
- `tabOrder()`: stops numbered from 1; positive tabindex first (by value, then document order), then document order.
- Not reached: `hidden`, `inert`, inline `display:none`/`visibility:hidden`, caller-hidden, closed `details` (but its first summary), closed `dialog`, disabled controls and a disabled fieldset's content (but its first legend), tabindex below 0. One stop per radio group (form + name): the checked one, else the first.
- `aria-hidden` stays reachable. `back`: before the previous stop in the document.
- `jumps(boxes, rtl)`: per stop, whether it starts above the previous stop's middle or before it on the same line (mirrored for right-to-left); null boxes are skipped.
- `headings()`: level, text (the name), `skipped`/`from`. `role="heading"` without `aria-level` reads as 2, `assumed`, and starts no skip, since the audit ignores it.
- `regions()`: banner, navigation, main, complementary, search, contentinfo, and region/form only when named; header/footer inside article, aside, main, nav or section are none; `name` from aria-labelledby, aria-label, title; `depth` in the others.
- `pictures()`: `kind` text, decor (`alt=""` on img, presentation/none) or none; `role="img"` (svg) named by its title or content.
- `controls()`: link, button (input submit/reset too, never a fault), field, frame, each with `name`.
- `read()`: the five lists over one walk. Where a script moves focus it says nothing.

## Which faults can it raise?
- Only `a11y_heading_order`, `a11y_img_alt`, `a11y_link_name`, `a11y_button_name`, `a11y_form_label`, `a11y_frame_title`, each where `pg_a11y_check_html()` (`includes/fn/a11y.php`) raises it: a placeholder is no label, a field inside or tied to a label (even an empty one) is labelled, a frame is named only by a label or title. The test runs both on the same markup and compares the counts per code.

## How fast is it?
- Node 22, 4 cores, 1,651 elements (50 named sections of six cards), median of 25 runs: `read()` 3.7 ms.
