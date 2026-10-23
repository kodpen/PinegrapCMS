---
name: designer-audit-form-action
description: Designer audit hint form_no_action: a form outside every system widget with no action is sent nowhere; what counts as wired, why it is a hint and not a ban, and the test.
---

## What does the hint catch?
- Components > Form > Form (`_cfPaletteBlock('form')`: `method="post" novalidate`, no action) dropped on a page was published as is: submitting POSTed to the same URL, the page came back empty and what the visitor typed was lost without a word. The Form group is meant for the Custom Form widget, whose renderer wraps the tree in its own `<form action="custom_form.php">` (the browser drops the nested start tag, the fields go with the widget's form).
- The block sample forms (contact, newsletter, login, notify, search; designer-block-forms.md) are the same case until wired, so they get the hint too.

## Where does it live?
- Page-level pass `_pageLevelIssues()` (style_designer.js), rule `form_no_action`, on the form node (badge, Go). It walks the page and the shared components like `cf_field_unnamed`; a node rule would not see a plain shared component (a shared header's search form).
- `_sdAuditFormGoesNowhere(n)`: a semantic `form` (tag in any case) with no non-blank `action` attribute and no `_bindings.action`. A blank action posts back to the same page, so it counts as none.
- Not flagged inside any system widget (`_swIsType(cfg.regionType)` of the shared_ref's `system_region_config`, inherited by components nested in it): the widget wraps the form (custom_form, login region, member forms, cart, express order) or binds it (search_form, coupon_form, account_security_form). An empty widget (`regionType` `''`) and a plain shared component are not widgets.

## Why a hint, not a ban?
- Owner decision: the Form items stay droppable anywhere (`canDrop` unchanged). The label says how to fix it: write an Action in the Form panel or use a Custom Form widget.
- The Form panel's Action box (`data-form-attr`) only re-rendered the canvas, so the audit kept the old finding until the next render; the handler now calls `scheduleValidation()`.

## How is it checked?
- `tests/designer_audit_rules_test.php` `…_form_without_action`: the real `_pageLevelIssues()` under Node with `_sharedCache` cases (plain component, empty widget, custom_form, login_form, a component inside a widget).
- Chromium: Form alone on a page → hint and badge; a Form item put into a Custom Form widget → no hint on it; Action `/contact-thanks` written → hint gone; contact/newsletter/footer newsletter blocks → one hint each.
