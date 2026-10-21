---
name: custom-form-settings-shortcut
description: Designer custom_form: "Form settings" shortcut from any node inside the widget, new page form seeded with the site address, silent-form warning in the panel and after publish.
---

## How does the operator reach a form's settings from the canvas?
- A click on a form lands on a control or a wrapper inside the widget, never on the widget's `shared_ref`, and the form-level settings (notification, auto-reply) live only in the `shared_ref` panel (`propsSharedRef` → "Custom Form Settings"). Before PR #PRNUM they were reachable only from the page tree "[System] …" row (found on kodpen.com, 2026-10-21).
- Now every node inside a custom_form widget (`_cfWidgetOf(node)`) gets a **"Form settings"** button on top of its panel (`_cfSettingsShortcut()`) and a `bi-ui-checks` button in its canvas toolbar (`tb._sdFill` in `buildNodeEl`).
- Both call `_cfOpenFormSettings(sid)`: selects the widget's placement (`_cfRefOf(sid)`: the page tree first, then other shared component trees), opens the "Custom Form Settings" section if it was collapsed and scrolls to it.

## What notification address does a new form start with?
- The site's own address (`EMAIL_ADDRESS`, sent as `sdDesign.siteEmail` from `includes/designer_screen.php`). `_cfSeedNotifyAddress(sid)` writes it into the active page's `formSettings.notify_email`; the server then turns `administrator_email` on (`pg_cf_write_form_settings()`: a non-empty address means on).
- Called where a page form comes into being: a custom_form widget created from the palette (`_createSystemWidgetNode`), a widget switched to custom_form in the panel, and the "copy for this page" fork of that switch.
- **Existing forms are never changed:** nothing is seeded when the page already has a form record (`formSettings.exists`), when an address is already there, when the widget shows another page's form, or when the page was seeded once in this editor session (`_cfNotifySeeded`, so an address the operator cleared stays cleared).
- Forms drawn before this change (empty address, auto-reply off) stay silent until someone fills them in; the warning below says so.

## When does the editor warn that a form notifies nobody?
- Silent = empty `notify_email` AND `confirm_email` off (`_cfSettingsSilent()`).
- In the panel: under "Notification email" (`#sd-cf-silent-warn`, toggled while the address is typed; the auto-reply switch redraws the panel) and under the "Form settings" shortcut.
- After a save/publish: one non-blocking warning toast per page per editor session (`_cfSilentWarned`), read from the settings the server returns in `forms[]` (`_pgTabsAfterSave`), only for pages written in that save that still carry their own form widget.
- **Never blocks:** some forms are meant to be quiet (a poll, a sign-up whose records are only read in the panel).

Related: docs/memory/designer/custom-form-options-and-rules.md, docs/memory/designer/custom-form-model.md
