---
name: bug-hunt-modules-endpoints-2
description: Module list of plans/bug-hunt.md, part 4: root endpoint groups optimize to zone by area word, one line each with its state; single endpoints in endpoints-2b
---

## Which root endpoint groups (optimize to zone) are hunted?
- Done: optimize, order #5115 #5125 #5170 #5178 #5202 #5210 #5215 #5270 #6153, page #5135 #5151, parasut, privacy, preview #6033, product #5257 #5355 #5371 #5393, recurring #5525, referral, region, registration #6041, remove #6041, return, search #6064, seo #6091, setting, shipping #6086, shopping #6113, short, software #6121, style #6180, submitted #6098, subscription, system #6111, theme #4725 #4739 #4764 #4775, translation, user #4489 #4505, verified, visitor, view #6116, waf #4601 #4620, workspace #6120, zone
- Lead hunted: barcode generation checked only product_barcodes, not other products' codes, in the panel generate and bulk assign, the shared generator and the API barcode (edit_products already checked both) #6664
- User management, second round: clean. `add_user.php`, `import_users.php` (role refused at or above the editor's own, in-file duplicates skipped), `delete_users.php` (lower role only, last enabled administrator kept), `login_as_user.php`, `view_sessions.php` (sign-out, lock, push rows: own or lower role), the users list quick edit (`includes/fn/user_list_edit.php`: role, rights, last administrator), the two-step reset of `edit_user.php`, `pg_user_delete()` (remembered sessions, second factor, identities, every `users_*_xref` row), the API user writes (administrator owner, never an administrator's or its own account) and the Workspace role proposal (administrator only, never an administrator or oneself)
