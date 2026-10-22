---
name: designer-audit-tree-rules
description: Designer audit's tree rules for empty links/buttons, image alt and form submit: what counts as a name, an alt decision and a submit control, and how the test runs them
---

## Where do these rules live?
- `_validationRules` in `assets/js/style_designer.js` ("HTML VALIDATION"), run node by node by `validateTree()` beside the page-level and canvas passes (designer-audit-contrast.md). The rules below ask `_sdAuditHasName()`, `_sdAuditAltDecided()` and `_sdAuditFormHasSubmit()` (`_sdAuditSubmits()` per node).
- `validateTree()` keeps `ctx._ariaHidden`, the count of `aria-hidden="true"` ancestors.
- `tests/designer_audit_rules_test.php` runs the cut-out `validateTree()`, rules and helpers under Node on node trees (skipped without Node).

## When is a link or button empty (`empty_btn`)?
- A content link/button or semantic `<a>`/`<button>` with no accessible name. A name is: its text prop (the ready-made blocks' `_bkLink()` model; the emitter prints `props.text` after a semantic node's children, a content link's before them), a bound text, aria-label / aria-labelledby / title (not blank), a tooltip title, or a child that has one: a text node (a visually hidden span counts), an image with a non-empty or bound alt, an element with its own label. An icon, markup-only text (`<i …></i>&nbsp;`) and anything under `aria-hidden="true"` give no name.
- Not looked at: a control that is itself or under `aria-hidden="true"`, Bootstrap's `.btn-close`. A shared component or raw HTML child is not read from the tree and is taken as named.
- Before, a semantic link was empty when it had no children: every `_bkLink` was flagged, an icon-only link was not.

## When does an image lack alt (`img_alt`)?
- When it carries no alt decision. Written words are one, and `alt=""` says it is decorative (marquee copy, thumbnail beside its headline, avatar beside the name), as the server's SEO check reads it (`seo_structure.php` `img_no_alt`). A bound alt, `role="presentation"`/`"none"` and `aria-hidden="true"` on the image or an ancestor are decisions too. No "decorative" switch exists; an empty Alt text field says it.
- No decision: no alt prop at all, and the palette image's placeholder. The image dropped from the palette starts with alt `Image` (`CONTENT.image.defaultProps`, printed as is); the rule flags `Image` and `_sdT('Image')` (`Görsel`), trimmed and in any case, so the operator is told to write one. The test checks the default is still `Image`.
- HTML import (`includes/designer_import.php`) leaves the prop out for an `<img>` without the attribute and keeps `alt=""` as written. Earlier imports carry `alt=""` there and are not flagged.

## What is a submit control (`form_no_submit`)?
- Anywhere inside the form, as the renderer prints it: a `<button>` with no type or `type="submit"` (a semantic node, or a heading content drawn with tag `button`; the renderer prints only the `_attrs` type, so typeless submits), an `<input type="submit">`/`type="image"` (`_attrs` type or `inputType`), or a Button component drawn as `<button>`/`<input>` with `btnType: 'submit'`.
- Not a submit: a `<button type="button">`/`"reset"` (before, any semantic button counted), a Button component drawn as a link (`btnElement` `a`, the default) or with `btnType` button, and the old content button (`contentType: 'button'`, which has no renderer; before, it counted).
- Ready-made blocks whose form button is a link Button component are still flagged, rightly (13: navbar-search, footer-newsletter(-2), newsletter-inline/split/card, contact-form/info/map, login-centered/split/minimal, error-404-search); their builders are a separate fix.

## What did it change on the ready-made blocks?
- Headless Chromium, every palette block built and audited: on the 29 blocks of PR #1169 the three rules went from 97 findings (72 empty link, 25 alt) to 0; over all 126 blocks from 120 to the 13 forms above. The placeholder and button-type checks changed no block's findings; they flag the palette image as dropped and a form with only a `type="button"`.
