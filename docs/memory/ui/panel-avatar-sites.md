---
name: panel-avatar-sites
description: Where the panel draws people as the Bootstrap 6 Avatar (step B9j slices) and where the picture of letters (pg_initials_avatar_url()) stays
module: contacts
---

## Which sites are converted?
- Header button and account menu (#2216); the Workspace screens, task card people, Workspace settings table (#2242); chat (`avatarNode()` in `chat_backend.src.js` and its hand-kept `.min.js` twin, `avatar_kind` from `pg_chat_avatar_kind()`), dashboard widgets 4 (presence dot), 28 and 30 (the Avatar fills the 34px row tile), the contacts list and the order screen (`pg_avatar_markup_for()`, the twin of `pg_avatar_for()`), the guest page (`workspace_guest.js`, own `AVATAR_THEMES`), the delegate tag (`ws_assistant_face()`), the contact edit screen's picture card (#2339).
- The designer toolbar keeps its own `.sd-presence-avatar` (editor chrome colours) but draws the letters (`initials` from `pg_collab_user_brief()`), not the picture of letters.

## What keeps the picture of letters?
- `pg_initials_avatar_url()` stays for e-mail, PDF and print (`print_order.php`), and for site output (Bootstrap 5.3.8, `widgets_account.php`), which has no `.avatar`. The account menu's quick-form preview `img[data-pg-contact-preview]` keeps it too: the chosen file replaces its `src`.

## How does the contact edit screen draw a contact without a picture?
- `edit_contact.php` puts `pg_avatar_markup_for(…, 'avatar-xl')` in the middle of a square box inside the picture card, so the card keeps the height the square picture of letters gave it (#2339). Both photo branches keep their `<img>`.
- Picking a picture still works: `software_image_picker()` empties `#software_image_picker_container` and adds the picked picture's card, Avatar included.
- 4rem is the largest Avatar without a class of its own; a bigger face needs a `.avatar.pg-avatar-…` size in backend.src.css.
