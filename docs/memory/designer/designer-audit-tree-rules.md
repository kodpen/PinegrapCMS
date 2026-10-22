---
name: designer-audit-tree-rules
description: Designer audit's tree rules for empty links/buttons, image alt and form submit: what counts as a name, an alt decision and a submit control, and how the test runs them
---

## Where do these rules live?
- `_validationRules` in `assets/js/style_designer.js` ("HTML VALIDATION"), run node by node by `validateTree()` beside the page-level and canvas passes (docs/memory/designer/designer-audit-contrast.md). The three rules below read their answers from `_sdAuditHasName()`, `_sdAuditAltDecided()` and `_sdAuditFormHasSubmit()`.
- `validateTree()` keeps `ctx._ariaHidden`, the number of ancestors with `aria-hidden="true"`, so a rule can tell that a node is hidden from assistive technology.
- `tests/designer_audit_rules_test.php` cuts `validateTree()`, the rule list and the helpers out of the file and runs them under Node on node trees (skipped without Node).

## When is a link or button empty (`empty_btn`)?
- Only a content link/button or a semantic `<a>`/`<button>` with no accessible name. A name is: its text prop (the ready-made blocks' `_bkLink()` model; the emitter prints `props.text` after a semantic node's children, a content link's before them), a bound text, aria-label / aria-labelledby / title (not blank), a tooltip title, or a child that has one: a text node (a visually hidden span counts), an image with a non-empty or bound alt, an element with its own label. An icon, markup-only text (`<i …></i>&nbsp;`) and anything under `aria-hidden="true"` give no name.
- Not looked at: a control that is itself or under `aria-hidden="true"`, Bootstrap's `.btn-close`. A shared component or raw HTML child is not read from the tree and is taken as named.
- Before, a semantic link was empty when it had no children, so every `_bkLink` was flagged (72 on the 29 blocks of PR #1169) and an icon-only link without a label was not.

## When does an image lack alt (`img_alt`)?
- When it carries no alt decision. Any alt string is one: words describe it and `alt=""` says it is decorative (the marquee copy, a thumbnail beside its headline, an avatar beside the name), the reading of the server's SEO check (`seo_structure.php`, `img_no_alt` only for a missing attribute). A bound alt, `role="presentation"`/`"none"` and `aria-hidden="true"` on the image or an ancestor are decisions too. There is no separate "decorative" switch; the empty Alt text field is how the operator says it.
- No alt prop at all is no decision. The palette image starts with alt `Image`; HTML import (`includes/designer_import.php`) now leaves the prop out for an `<img>` without the attribute and keeps `alt=""` as written. Designs imported earlier carry `alt=""` for those images and are no longer flagged.

## What is a submit control (`form_no_submit`)?
- Anywhere inside the form: a semantic `<button>` (any type, as before) or content button, an `<input type="submit">`/`type="image"` (`_attrs` type or `inputType`), or a Button component drawn as `<button>`/`<input>` with `btnType: 'submit'`.
- A Button component drawn as a link (`btnElement` `a`, the default) or with `btnType` button does not submit: older blocks whose form button is such a link (search navbar, newsletter footers, contact and login forms, 404 search) are still flagged, rightly.

## What did it change on the ready-made blocks?
- Headless Chromium, every palette block built with `createFromPalette()` and audited: on the 29 blocks of PR #1169 the three rules went from 97 findings (72 empty link, 25 alt) to 0; over all 126 blocks from 120 to 13, the 13 being the forms above. No block gained a finding.
