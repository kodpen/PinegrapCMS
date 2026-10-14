---
name: erp-account-contact-link
description: ERP account to contact/user link: contact_id is the truth, mirror column, link function, account form card, prefilled add screen, bulk account opening.
---

## Which side is the truth of the account-contact link? (no schema change)
- `erp_accounts.contact_id` is the truth; `contacts.erp_account_id` is a mirror. Read from the account side (`erp_contact_summary()`); write both sides together with `erp_account_link_contact()`.
- One contact → one account; a contact linked to another account is rejected; the old contact's mirror is cleared.
- User chain: `user.user_contact → contacts.id → erp_accounts.contact_id`. `edit_user.php` has no ERP button; it is reached from the contact screen.
- `erp_account_save()` writes `contact_id` only when the key exists: see erp-account-save-partial-writes.

## How does the account form handle the link?
- `erp_account_form_cards($liveform, $with_opening, $currency_locked, $contact)`: the fourth parameter opens the "Bağlı Kişi" card (`erp_account_form_contact_card()`); the fifth is the account row (for the e-invoice state line).
- Search `get_erp_contacts.php?q=` (ERP gate); `data-erp-own-account` in the result greys out contacts linked to another account.
- In `edit_erp_account.php` the link is attempted BEFORE saving; a rejection stays on the `contact_id` field and nothing is written.
- `add_erp_account.php?contact_id=N` opens pre-filled via `erp_account_data_from_contact()` (the bridge `erp_account_for_contact()` uses the same read); if the contact already has an account it redirects there.

## How are accounts opened in bulk?
- `erp_accounts.php` `erp_action=sync_contacts` → `erp_accounts_sync_contacts()` (confirmed POST): contacts with N orders but no account. Not run on dev.
- The candidate query uses `INNER JOIN contacts`: `orders.contact_id` can point at a deleted contact (20 such orders on dev); if the count and the number opened disagree, that is the reason. `skipped` = exists but nameless card.

Related: docs/memory/erp/erp-account-save-partial-writes.md
