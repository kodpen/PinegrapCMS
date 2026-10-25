---
name: erp-readonly-accountant-right
description: The ERP read-only right for an accountant (user.manage_erp_readonly, 4.78): what the gate refuses, its areas, USER_ERP_READONLY, the writers outside ERP screens, the Workspace's erp_readonly.
---

## What does the read-only right allow?
- `user.manage_erp_readonly` (2026.4.4, step 4.78), a role-3 switch that sits on `manage_erp` ("Read only (accountant)" on the user screens): every ERP screen to read, the accountant's pack to build and download, nothing to issue, pay or change.
- `USER_ERP_READONLY` (`includes/fn/auth.php`) = `USER_MANAGE_ERP` and role ≥ 3 and the column set; a role below 3 is never read-only.
- The sign-in path reads the column only behind `pg_user_has_erp_readonly_column()` (`includes/authentication.php`), so a site whose files are newer than its schema still signs in.

## What does `validate_erp_access()` refuse it?
- The areas `settings` and `write`, and every POST whose area is not `read` or `accountant`, with "Your account can read the ERP but not change anything in it." Every ERP form reaches the gate before its screen acts on it, so a sent form is refused there.
- A new screen whose form only reads (a filter posted as a form, an export) passes `'read'`, the accountant's pack passes `'accountant'`; otherwise the accountant is refused on it.
- A request that changes something without a form (a job run by hand) passes `'write'`, so a GET cannot slip past.

## What must screens and other modules do?
- On ERP screens hide every button that changes something when `USER_ERP_READONLY` is set (invoice, account and stock screens do).
- Outside the ERP screens, every place that writes ERP data checks `empty($user['manage_erp_readonly'])` for role 3: the order screen (`$erp_writes` in `view_order.php` hides the bar's invoice and delivery note buttons; the documents card still draws its steps, and the gate refuses their POST), the contact card (no Open an Account), the counter sale (`local_sale_can_erp()`: no ERP side), the Workspace (below), and the external API's ceiling (`erp_owner_scopes()` in `includes/erp/api.php`: `erp:read` and `erp_cash:read` only).
- Quick account and taxpayer lookup endpoints (`erp_account_quick.php`, `erp_taxpayer_lookup.php`) refuse it as well.

## How does the Workspace honour it?
- `ws_viewer()` carries `erp_readonly` (role ≥ 3, `manage_erp` and `manage_erp_readonly`, as `USER_ERP_READONLY`), from a `validate_user()` array, a user row or the API owner alike; `ws_change_viewer_from_row()` (behind `ws_change_viewer_for_id()`) reads it for another person: whoever a proposal waits for, a bulk change's starter, a scheduled action's creator.
- `ws_change_erp_readonly_refusal()` refuses it every kind in `ws_change_erp_types()` (`erp_account`, the kinds asked with an ERP right) with the gate's sentence. `ws_change_allowed()` asks it, so the drawer, proposals and Apply (panel and API), bulk, scheduled changes and the kinds Claude and Pinegrap AI are told about leave ERP accounts out; `ws_change_write()` refuses it too.
- Reads stay: tags and summaries need only `erp`; Claude's record route asks with `ws_change_reader()`. Also gated: the invoice draft from a channel's time (`ws_task_time_can_invoice()`) and the quote copy link (`ws_screen_config()`).
- Before the fix a read-only accountant changed or created ERP accounts from the Workspace drawer or a change card. Test: `tests/workspace_erp_readonly_test.php`.

Related: docs/memory/erp/erp-module-basics.md, docs/memory/core/permission-columns.md
