---
name: contact-delete-refusals
description: A contact a user account holds (user.user_contact) or an ERP account is linked to is not deleted, in the panel (Edit Contact, Modify Contacts, the list's mass delete) or the API (#5267)
module: contacts
---

## Which contacts are not deleted?
- One a `user` row holds (`user.user_contact`: the account's own contact) and one an `erp_accounts` row is linked to (`contact_id`, behind `pg_schema_has('erp_accounts')`). Deleting either leaves the holder pointing at nothing: the sign-in SMS code, the membership views and the ERP chain (`user_contact → contacts.id → erp_accounts.contact_id`) break. Same rule as the merge, which always keeps a contact a user holds (`contact-holders.md`, `survivor`).
- Architect decision (#5267, option A): refuse, never delete with a warning; the account is deleted (or the contact merged into another) first.

## Where is the rule?
- `pg_contact_delete_refusals(array $ids)` in `includes/fn/contacts.php` (pure part `pg_contact_delete_refusal_list($ids, $user_rows, $erp_rows)`): contact id => `reason` (`account` first, else `erp`) and `holder` (username or ERP title). `pg_contact_delete_refusal_text()` gives the sentence, `pg_contact_delete_refused_notice()` the bulk notice.
- `edit_contact.php`: a refused Delete is an error screen (409) naming the account. `edit_contacts.php` (Modify Contacts › delete) and `view_contacts.php` (mass delete of the view): refused contacts are skipped (left out of the Audit Trail too) and a warning counts them and names up to ten holders; the rest are deleted.
- `api_customers_delete()` asks the same function: 409 `customer_has_account`, else `customer_has_erp_account`.

## Tests?
- `tests/contact_delete_refusals_test.php`: the pure list and that the three screens and the API ask it before their DELETE.
