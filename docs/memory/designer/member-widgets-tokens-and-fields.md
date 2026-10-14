---
name: member-widgets-tokens-and-fields
description: Membership widget tokens/sections, Google rules, core vs contacts-column fields, login-screen redirect, register-link tri-state, reset screens, page-name box.
---

## Which tokens and sections does each widget have?
Token = address/name; section = server-produced block.
- Login: sections `google_signin`, `forgot_password_link`, `register_link`; tokens `__site_name`, `__redirect_url`, `__forgot_password_url`, `__register_url`, `__google_signin_url`.
- Reset: section `password_hint`; tokens `__site_name`, `__password_hint`, `__back_url`.
- Register: sections `captcha`, `password_rules`, `google_signup`, `login_link`; tokens `__site_name`, `__login_url`, `__google_signup_url`, `__password_rules` (HTML), `__opt_in_label`.
- Membership: same as register minus `captcha`, plus `__member_id_label`. Google is not drawn in `'membership'` mode nor on the Google return (`google_membership_pending`); on that return the password is not `required`.
- A designer wanting their own look binds `href` to the token instead of using the section.
- Google: from login only sign-in (`pg_google_signin_url($send_to, 'none')`), from the register widget `'create'`. `pg_google_signup_allowed()` also counts a live page carrying a register/membership widget as an entrance (`pg_member_widget_published`).

## Which controls are stored in register / membership widgets?
- A control is either core or a contacts column. Core names `MEMBER_CORE_FIELDS` (JS) <-> names read by `pg_member_register()` / `pg_member_activate()` — lockstep. Every other control is NOT stored unless linked via Options -> Üyelik Alanı -> Rehber to a `contacts` column.
- The link is the custom form's `_cf.contact_field`; do not invent a second field. Panel is `_memberFieldSection`; the custom form's `_cfFieldSection` is tied to `custom_form`, do not extend it.

## How do page pickers work here?
- `redirect_page_id`, `forgot_password_page_id`, `register_page_id`, `login_page_id` use `_swPageOptions` / `_swPageVal`; `types` filters tabs carrying the relevant widget. The register link is one tri-state picker: none / `legacy` (`show_register_link: true`, `register_page_id: 0`) / a page. Renderer reads `(int)`; `tab:` resolution is in `pg_designer_resolve_tab_values`' general walk.

## What is the `screen` state in the reset widget?
- `screen` is the reset state machine: `''` = form; `password_hint` = hint + same form (hidden `screen=password_hint` still submits); `confirm` = controls drop, notice stays, form not wrapped.
- The confirmation notice is identical for known and unknown address (owner decision, 2026-08-31): never produce "address not found" on the widget side.

## Why must the page-name box not be overwritten while open?
- Page name is written from two places: a new page opens the inline name box in the tab (`_pgTabsRename`); the `#sd-page-name` handler, if that box is open, writes the box's value and does not rewrite the label via `textContent`. Reason: Chromium fires `blur` on a removed focused element while it is STILL attached, the box applies its empty value back and the typed name is lost; `isConnected` cannot catch it.

Related: docs/memory/designer/member-widgets-handlers-and-forms.md, docs/memory/designer/designer-page-pickers.md
