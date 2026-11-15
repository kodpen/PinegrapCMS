---
name: look-people-screens
description: Users, contacts, privacy and campaign screens in look A (S2, #9692): titles, group labels, field spans per field, campaign cards unwrapped, what stayed
---

## Which screens moved?
- Create User, Import Users, Sessions; Create and Edit Contact, Contacts' Advanced Filters, Edit Contact Group, Import Contacts; Privacy export and erase; New and Edit Campaign, SMS campaign, campaign profiles and their import.
- WhatsApp template, the Messages area and the lists (table driver) were already look A and are untouched.

## What changed on them?
- Card titles: the upper-case `text-uppercase h5 fg-primary fw-bold` is `pg-title-section` (an `h2` in the header, or the header div with a `<span>` when the header holds more).
- The "Optional" line of the Account card (Create User) and the Advanced Filters headings of Contacts are `.pg-group-label`; the filters' green `fg-success fw-bold` is gone.
- Fields on the 12-column grid (`look-field-widths.md`): Username, nickname, company, job title, the two address lines, Contact Group Name, From Name the whole row; First and Last name 6 + 6; e-mail and start page `md:col-6`; city, state, zip and country in one row of quarters (3 each, zip before country); phones and faxes in one row of 4s.
- Codes (tax number, member id, Parasut id, affiliate code) 4; reward points, dates, times, commission rate 3 (its right-aligned inline style stays, pinned by `tests/rate_boxes_store_typed_test.php`); selects 6.
- Salutation and suffix are short and take 4, each alone in its row (`pg-field-span-4`). `pg_contact_form_text_field()` takes the column classes as its last argument.
- Campaign screens: `pg_email_campaign_card()` and `pg_email_campaign_layout()` put the cards straight in their column (no `div.row > div.col-12` around them), so they are look cards (`look-edit-screens-wrapped-cards.md`); New Campaign, Edit Campaign and the SMS campaign got `pg-measure-edit`.
- Inline colour to a token: the contact picture well is `--pg-surface-inset`.

## What did the architect's look add? (channel 25, 4599)
- Help text (`form-text`) runs from the field's left edge, never `text-end`; text in an input starts at the left, only a figure is end-aligned: the e-mail inputs (contact E-mail, campaign From, Reply-to, BCC, the extra address) lost `text-end`.
- Architect (2026-11-15): S2 stands in light and dark with these fixes; the architect's look, delegated by the owner's 2645.

## What stayed as it was?
- Edit User (`edit_user.php`): a screen of `workflow/decisions-made-panel-3.md`, in no group; its look stays as on development (architect, channel 25 message 4371).
- Buttons (LM2), a list's title with its count, segmented filters; the Summary values (`dd.fw-bold`, shared by every form); switches and radios; the permission group colours of `includes/user_permissions.php`; the Sessions tables (not on the driver).
