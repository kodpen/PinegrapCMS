---
name: bug-hunt-modules-endpoints-2c
description: Module list of plans/bug-hunt.md, part 4c: root endpoint groups sms, state and tax, one line each with its state; the software group's findings
---

## Which root endpoint groups (sms, state, tax) are hunted?
- Endpoints `sms` (3: edit_sms_campaign, sms_code, view_sms_campaigns): done #6093 (fields posted as lists: a list `start_at` stopped the save, a list name or text was saved as "Array", a nested delete id deleted campaign 1); sms_code clean
- Endpoints `state` (3: add_state, edit_state, view_states): clean
- Endpoints `tax` (3: add_tax_zone, edit_tax_zone, view_tax_zones): clean

## What did the software group's hunt find?
- `software_update_check.php` (#6121): the version answer of the update server added another "Software update available" row to the bell on every check and every opening of `software_update.php`, and an answer offering nothing left the old row; the answer now deletes the old row first, as the vendor API path does.
- `software_repair.php` and `software_update.php` clean.
