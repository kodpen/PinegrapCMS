---
name: designed-page-comment-names
description: Which name a signed-in member's comment gets: the contact's display name (else Member) on visual-designer pages, the username on legacy pages; the name select's choices and forged values
module: comments
---

## Which name does a member's comment get on a designed page?
- The account contact's display name, `pg_contact_display_name()` (`includes/fn/contacts.php`): first + last name, else the nickname; with neither, `lang('Member')`. Never the login username (half a credential; a member's defaults to the e-mail's local part). Owner decision 2026-10-23 (7r): new comments only.
- Legacy pages keep the username. `pg_comment_member_name()` (`includes/fn/comments.php`) decides; the form (`get_page_content.php`, the `<strong>` or the select) and `add_comment.php` both ask it, so what the member sees is what is stored. The contact is read by user id (`pg_comment_member_contact()`). Cut to 100 characters (`comments.name` is varchar(100)).

## Who still types a name?
- Unchanged on every page: a signed-out visitor, role < 3, a role-3 user with edit rights on the page's folder, the record's form editor. Optional field, maxlength 50, empty stores `Anonymous`. Staff get it prefilled with the same display name.
- So a role-3 editor without rights on the magazine's `registration` folder is a member on `conversation`: their inbox reply is signed with their display name or Member.

## What does the name select offer and accept?
- With `comments_allow_user_to_select_name` on (any page), `pg_comment_name_choices()`: `username` (the select-off name above: the display name on designed pages, never the username there), `first_name` when set, the four combined options when both names are set (legacy formulas, character initials), `anonymous` (the literal `Anonymous`). Labels are `h()` of the value, keyed by label, so equal labels collapse into one option.
- A posted `name_type` the member is not offered (empty, unknown, an array, `full_name` without a last name) counts as no choice: the select-off name. The posted `name` field is never read for them.

## How is a designed page told?
- `pg_sw_page_is_visual($page_id)` (`includes/fn/widgets_account.php`): a visual design style or a stored page tree. The comment's own page decides (the posted `page_id`; in the form `$system_region_properties['page_id']`), also in a region showing another page's comments. No page row: legacy rule.

## Which language is a stored "Member" in?
- The posting form's: a page drawn in another site language adds `pg_lang` to every POST form (`includes/translate/render.php`) and `init.php` answers in it; on a source-language page both use the software language ([[software-text-in-site-languages]]). A mismatch is possible only when the router drew the page in another language than the config's (`?local=`, or no `DEFAULT_SOFTWARE_LANGUAGE`). Editors fix a name under Comments -> Edit Comment.

## What stays as it was?
- Stored comments (no data step; a later rename does not touch them). The staff e-mail's `Display Name: <stored> (<username>)`, so moderators still find the account. The `(username)` beside names for edit-access viewers. Verified reviews (only on legacy catalog pages, which keep the username). The privacy export (by `created_user_id`).
- Readers of `comments.name` (e-mails, `newest_comment_name`, site search, panel list, API) follow without code changes.

## Which symptoms did it fix?
- 7r: members' comments and editors' inbox replies on designed pages were signed with the login username; the magazine's staff notes now say the display name (template 1.0.1).
- On every page, with the select on, a member could store any typed name by posting an unknown `name_type` with a `name`, or `Jane .` through a combined option the form did not offer.
- Tests: `tests/comment_member_name_test.php`.
