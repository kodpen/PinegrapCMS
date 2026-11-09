---
name: approve-affiliate-shared-form
description: approve_affiliate.php draws its request card, Summary and save bar through includes/forms/approve_affiliate_form.php (wide layout, step B11); what the screen keeps, the website link rule
---

## What does includes/forms/approve_affiliate_form.php hold?
- The Approve Affiliate screen (the link in the affiliate request e-mail) in the wide layout (`edit-screen-wide-layout.md`, step B11). Before, it was one centred card with a sentence and two buttons.
- `pg_approve_affiliate_form_card($contact, $approved)`: the form column, an Affiliate Request card (name, e-mail, company, affiliate name, website, phone, city, country; empty facts left out by `pg_approve_affiliate_form_facts()`, the business phone before the mobile phone, the country named by `pg_geo_country_name()`) and the Approve Affiliate card with the existing confirm sentence, or the already-approved sentence.
- `pg_approve_affiliate_form_status(...)`: the sticky Summary: status badge (Pending Approval / Approved), affiliate code (a dash before one exists), Last Modified with the user, a Go to Contact link. `pg_approve_affiliate_form_layout()`: the row and the sticky save bar.
- Tests: `tests/approve_affiliate_form_test.php`.

## What stays in the screen?
- The gates (`validate_user()`, `validate_contacts_access()`, `validate_contact_access()`), the contact load (now with the request's columns and the last editor's name), the POST approval through `pg_affiliate_approve()`, the notices and the redirect to the contact.
- The notice is queued under the `edit_contact` liveform, which Edit Contact prints under its toolbar and removes once drawn. It went to a `view_contact` form nobody read, so the screen showed nothing and the notice waited in the session for a site page's messages region (#7734).
- The GET shows only; the approval is a POST with the session token (the e-mail link cannot carry it). Only a pending request gets the form tag and the Approve Affiliate button in the save bar; an approved one gets Go to Contact there and no form.
- The Cancel button is gone: the back arrow and the breadcrumb lead back (`back-arrow-no-copies.md`).

## Which website is linked?
- `pg_approve_affiliate_form_website_url()`: an `http://`/`https://` address as typed, a bare host with `https://` before it; another scheme (`javascript:`, `data:`), a `//host` start or text with spaces is printed as plain text. The website is what a visitor typed in the sign-up form.
