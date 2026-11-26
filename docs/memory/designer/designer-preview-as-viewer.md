---
name: designer-preview-as-viewer
description: What PW2c (#11728) built: pg_preview_as_viewer() and its helpers, which renderers read it, the sample account's rows, the mask, what is left to the operator
module: designer_preview_render
---

## What reads the chosen visitor?
- `pg_preview_as_viewer()` in `designer_preview_render.php`: `{preview, sample, signed_in, id, contact_id, role, member, username, email, first_name, last_name}`. Outside a Preview widget: the request's `USER_` constants. Inside: signed out (nobody), signed in (the sample account, id 0, role 3), member (signed in and a member), first visit and returning (signed out, `returning` true for Returning only, #11746). Outside, `returning` is the `pg_ret` cookie's.
- `pg_preview_as_view_access($folder, $grant)` stands for `check_view_access()` in every widget renderer; inside a Preview the rule is `pg_preview_as_view_rule()`: public for all, guest and registration signed in or with `$grant`, membership and private for the member only.
- `_pg_acct_signed_in()` and `_pg_member_session_signed_in()` (the "already signed in" branch of login, forgot password, sign-up) answer the viewer's `signed_in` in a Preview.

## Which renderers read it?
- Signed in or out: the account family (logout, change password, profile, e-mail preferences, address book, my account and its extras, the login region with its staff and panel links), form list's viewer filter, `pg_sw_submitted_form_visible()`, the search's folder narrowing, comments and recent comments, the menu's and gallery's folder checks, billing, shipping address, express order, cart, booking, catalog's back-in-stock e-mail, folder view's start page.
- The login region and the comments widget were drawn signed out in every background render; in a Preview they now draw the chosen visitor, still with no session, token or form state (`audience['session']` stays false).

## What do the audience conditions read?
- `pg_audience_value()` asks `pg_preview_as_audience_value($kind, pg_preview_as_viewer())` first while a Preview widget is drawn, before the background rule and outside its cache: returning from the chosen case, never the cookie; member from the chosen member; every other kind '' (no group for the sample account; the operator's country, device, UTM, experiment are no visitor's). No cookie is set (PW2d, #11746).

## What does the sample account draw?
- `pg_preview_as_sample_account()`: the canvas's Alex Morgan (`lang('Alex Morgan')` split as the canvas splits it), its contact row in `contacts` columns, two address book recipients (Sam Carter's studio first). `pg_preview_as_sample_recipient($name)` finds one.
- Orders, invoices, bookings, store credit, subscriptions, tickets, watched records, comments of its own: none, drawn as a new account's; no statement runs with id 0.

## What is masked?
- A form item view's record in a signed-in Preview: `pg_preview_as_mask_rows()` masks the e-mail (type `email address` or contact field `email_address`), phone and fax, and address lines, city, state, zip by the field's `contact_field` (`pg_preview_as_mask()`: first character kept, an e-mail keeps its domain).

## What stays the operator's?
- Edit chips, the editor short-circuit of the form list, the staff-only cart and checkout options, the offline payment right, the ad and menu edit rights: class three of the census, untouched.
- `tests/designer_preview_render_test.php` holds the census's functions to no bare `USER_ID`, `USER_LOGGED_IN`, `USER_CONTACT_ID`, `USER_EMAIL_ADDRESS` or `USER_USERNAME`.

Related: docs/memory/designer/designer-preview-as-visitor.md, docs/memory/designer/designer-preview-as-readers.md
