---
name: bug-hunt-modules-fn-1a
description: Module list of plans/bug-hunt.md, part 1a: includes/fn/ modules collections to editor, one line each with its state
---

## Which includes/fn/ modules (collections to editor) are hunted?
- Done: collections #4650, comments #4599, commission_list, config_text, connect #5245 #7100, consent #4577 #4587 #7092, contact_list, contacts #4624, content #5343 #5364 #5387 #5443 #5515 #5864 #5923 #6090 #6151, core #5935 #6018 #6042 #6089, cron #5889 #5930, currency_rates #5924, custom_form #5926, dashboard, db_maintenance #5984, design_templates_custom #5976, design_themes #6021, designer #6034 #7025, designer_fx #6050, designer_theme_vars, designer_vocab, ecommerce #6030 #7024, editor #6032 #6065
- Second round (2026-11-05): contact_list clean (with view_contacts.php, edit_contacts.php and the quick edit).
- Second round (2026-11-06): custom_form clean (the private access expiry in calendar days, the visitor rules, the submission limits and the mail links read again; the designer save and the shipping and billing forms too).
- Second round (2026-11-06): ecommerce #7568 (read: the changes since 2026-11-01 in full, with add_order_item, check_inventory, get_product_groups, process_order_cancellation, pg_order_restore_cancelled and add_pending_offers whole; the rest of the module only by a request-input grep).
- Second round (2026-11-06): contacts #7599 #7600 (read: the changes since 2026-11-01 in full (pg_contact_count_sql), with merge_contacts, pg_contact_holders_move, pg_contact_quick_save, pg_contact_photo_store, pg_checkout_fill_contact_tax and pg_affiliate_approve whole).
