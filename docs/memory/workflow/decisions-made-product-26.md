---
name: decisions-made-product-26
description: Decisions list, product part 26 (2026-11-04 on): the starter dumps stay deletable in the Backup Manager, with a confirmation that names the consequence
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Starter dumps in the Backup Manager: deletable, with a warning (#6445, owner, 2026-11-04)
- Owner (2026-11-04, #6445, option B): `english_default` and `turkish_default` keep their Delete button in `backups.php`; for those two names the confirmation text says what follows: the language is gone from a later install made from this copy (`get_available_starter_sites()` lists only the folders on disk) and the panel does not bring the dump back. Reason: on a running site the dumps have no use and a manager may want the space (about 3 MB for the two); the write side stays refused by `pg_backup_name_reserved()` (`platform/backup-manager-names-and-list.md`). Refused: hiding Delete and refusing the POST for the two names. One `bug` work item in `backups.php`: the row markup and the new string added with `php tools/tr_add.php`.

## ERP account card: a tax number VIES does not confirm only warns (#6489, owner, 2026-11-04)
- Owner (option A): when the card's country and the store's are different member states, an id VIES does not know or one not shaped like a VAT id gets a warning on the screen the save goes to; the card saves (`erp_account_form_check_vat()` with `$refuse_invalid = false`, PR #6493, SF6b2b). Reason: the card is the accountant's record and may hold a national number that is no VAT id; the reverse charge is decided at checkout from the order's own check (`commerce/eu-vat-check.md`), never from the card. Refused: B, refusing the save.

## Edit Product > Duplicate on a variant set member: the copy leaves the set (#6524, owner, 2026-11-04)
- Owner (option a): `duplicate_product.php` drops the copy's links to `display_type = 'select'` groups after the copy when `pg_pb_variant_group_id($id)` is non-zero; attribute options and other group links stay; `duplicate_product()` is unchanged (Duplicate Product Group relinks on purpose, `commerce/product-group-copy.md`). Reason: the copy is an independent product sold at once and the set keeps one member per combination; the set screen adds it back when wanted. Refused: a disabled member without a combination (b), refusing the action (c). `bug` work item.

## Issued invoice: the note is internal, never printed on the issued copy (#6541, owner, 2026-11-04)
- Owner (option a): the note saved on an issued invoice stays an internal note; the issued PDF, Download, the e-mail attachment, the public link and the accountant pack keep the copy stored at issue; the Notes card says so. Reason: the issued copy is the evidence handed out and does not change after issue. Refused: re-rendering a new copy (b), a read-only note (c). Architect: the save moves into an `includes/erp/` function (`erp_invoice_notes_save()` or alike) announced through `erp_event()`, so the audit trail records it (`erp/erp-api-events-and-testing.md`). `bug` work item.

## Cancelled invoice: PDF and Download stay, with a CANCELLED ribbon (#6542, owner, 2026-11-04)
- Owner (option b): on a cancelled invoice `edit_erp_invoice.php` keeps PDF and Download, and `get_erp_invoice_pdf.php` renders the document live with a cancelled ribbon (a new `label.*` placeholder the templates print) instead of the kept copy; the public link's 404 and the mail card's refusal stay; no schema change. Reason: the operator and the accountant still reach the document, and nobody can pass it on as a valid invoice. Refused: hiding the buttons (a), handing out the unmarked copy (c). `bug` work item.
