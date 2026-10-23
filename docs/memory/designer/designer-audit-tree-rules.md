---
name: designer-audit-tree-rules
description: Designer audit's tree rules for empty links/buttons, image alt, form submit and empty headings: what counts as a name, an alt decision, a submit and a heading, and the test
---

## Where do these rules live?
- `_validationRules` in `assets/js/style_designer.js` ("HTML VALIDATION"), run node by node by `validateTree()` beside the page-level and canvas passes (designer-audit-contrast.md). Helpers: `_sdAuditHasName()`, `_sdAuditAltDecided()`, `_sdAuditFormHasSubmit()` → `_sdAuditSubmits()` → `_sdAuditTypeSubmits()`. `ctx._ariaHidden` counts `aria-hidden="true"` ancestors.
- `tests/designer_audit_rules_test.php` runs the cut-out `validateTree()`, rules and helpers under Node (skipped without Node).

## When is a link or button empty (`empty_btn`)?
- A content link/button, a text (heading) node drawn with tag `a`/`button`, or a semantic `<a>`/`<button>` with no accessible name. A name: its text prop (`_bkLink()`'s model), a bound text, aria-label / aria-labelledby / title (not blank), a tooltip title, or a child that has one (a visually hidden span counts; an image with a non-empty or bound alt). An icon, markup-only text (`<i></i>&nbsp;`), an `<svg>` and anything under `aria-hidden="true"` give none.
- Taken as named, not read from the tree: a shared component, raw HTML, and content the server draws when the page is served (`custom_php`, `messages`, `applied_offers`, `breadcrumb`, `variant_attr`, `carousel`).
- Not looked at: a control that is or sits under `aria-hidden="true"`, Bootstrap's `.btn-close`.

## When does an image lack alt (`img_alt`)?
- When it carries no alt decision. Words are one; `alt=""` says decorative, as the server's SEO check reads it (`seo_structure.php`). So are a bound alt, `role="presentation"`/`"none"` and `aria-hidden="true"` on it or an ancestor.
- No decision: no alt prop, or the palette placeholder: the palette image starts with alt `Image` (`CONTENT.image.defaultProps`, printed as is), so `Image` and `_sdT('Image')` (`Görsel`), trimmed, any case, are flagged. The test checks the default is still `Image`. A hand-written `Görsel` is flagged only in the Turkish editor.
- HTML import (`includes/designer_import.php`) leaves the prop out for an `<img>` without the attribute and keeps `alt=""` as written.

## What is a submit control (`form_no_submit`)?
- Read as the browser reads the printed type: keyword in any case, not trimmed. A `<button>` submits unless its type is `button` or `reset`; a missing, blank or invalid type falls back to Submit. An `<input>` submits only with `submit` or `image`.
- The printed type: a semantic `<button>`/`<input>` or a text node drawn as `button` print only the `_attrs` type; a Button component drawn as `<button>`/`<input>` prints `btnType` (`button` when unset; `''` prints `type=""`, which submits). There is no `inputType` prop. Drawn as a link (`btnElement` `a`, the default) it does not submit, nor does the old content button (`contentType: 'button'`, no renderer).
- The ready-made block forms all have a real submit now (designer-block-forms.md). A form that is sent nowhere is the page-level `form_no_action` hint (designer-audit-form-action.md).

## When is a heading empty (`heading_empty`)?
- A text (heading) node only when it prints as `h1`–`h6`: its `tag` in any case, or `h2` when the tag is missing or malformed (the renderer's fallback). Drawn as a button, span, div or `p` it is not one: the carousel's indicators and arrows and the toast's dismiss button gave 6 false "Empty heading" warnings. A semantic `h1`–`h6` is empty with no children, text or bound text.

## How was it checked?
- Headless Chromium, every palette item (126 blocks, 29 components, 17 contents) built, audited and compared with the DOM (AX names, submit state, empty `h1`–`h6`): before, 120 findings on the blocks plus the 6 headings; now only the 13 forms. Hand-made trees through the PHP renderer (`_render_tree_node()`) agree with Chromium on every case.
