---
name: audiences-doors
description: Hand doors of a contact group filled by rules: which paths refuse or skip a dynamic group, API/MCP 409 contact_group_by_rule, the guard functions, readers and erasure
module: audience_doors
---

## Which guard does a door ask?
- `includes/fn/audience_doors.php`. `pg_audience_hand_sql($column)` is the SQL test `(kind <> 'dynamic')` (`(1 = 1)` before the 11.0.155 step): put it on the door's "does the group exist" query so a dynamic group reads as absent.
- `pg_audience_group_is_dynamic($id)` for one id; `pg_audience_hand_ids($ids, pg_audience_dynamic_group_ids())` splits a posted list into `hand` and `rule` (pure, tested); the dynamic ids are read once a request.
- `pg_audience_door_message()` is the one staff text.

## Which doors refuse?
- Staff: the contact card save (`pg_contact_form_all_groups()` leaves dynamic groups out, from the editor step), the bulk Organize on the contact list (`edit_contacts.php`: neither adds to nor removes from them, shows the message), contact and account imports (`import_contacts.php`, `import_users.php`: not offered, not written; an account may still be given a dynamic group to manage).
- Storefront, in silence: a form's contact group and the membership group (`custom_form.php`), a confirmed double opt-in (`pg_fs_confirm()` records the opt-in, no xref row), product sales (`pg_order_complete_contact_groups()`), the Orders group (`submit_order.php`, `express_order.php`, `billing_information.php`), the registration group (profile, e-mail preferences, sign-in, `auth.php`, contact creation in `contacts.php`), the affiliate group, a group ticked on e-mail preferences (the opt-in is kept).
- API and MCP (same handler): `POST /contact-groups/{id}/members` and `POST /customers` with a dynamic `contact_group_id` answer 409 `contact_group_by_rule` before the dry-run stop (`api_contact_group_hand_or_fail()`).

## What is left as it was?
- Readers: campaigns, SMS campaigns, the account's group list, exports, `GET` members read the xref rows whatever their source.
- Erasure deletes a contact's xref rows and recomputes nothing.
- A merge (`merge_contacts()`, both blocks) re-inserts each group row with a source from `pg_contact_merge_group_source()` (pure, `includes/fn/contacts.php`): a hand, form or product row of any merged contact wins, else `rule`; before the upgrade no source is read or written (#6307). Before, every row came back `hand`, so a merged contact stayed in a dynamic group the rule no longer selected.
- Hand, form and product rows of a group switched to dynamic stay: the recompute never touches them (#6319). The switch review (#6418) shows their count and offers keep (default), remove or let the rules decide them; `pg_audience_switch_review()` (pure, `includes/fn/audiences.php`) holds the count query and the two actions' statements, `pg_audience_switch_apply()` runs the chosen one after the switch is saved.
- Remove announces the contacts that leave (no rule row beside the removed one) as `members_changed` removals; turn into `rule` changes no membership and announces nothing: the next recompute deletes the turned rows its rule does not select and announces that.
- The form and product editors still list every group; the sale or submission skips a dynamic one.

## Tests?
- `tests/audiences_doors_test.php`: the split, the SQL test, and a scan that every door file names its guard.

Related: docs/memory/core/audiences-rules.md, docs/memory/core/audiences-recompute.md
