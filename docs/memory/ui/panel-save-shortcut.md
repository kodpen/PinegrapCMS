---
name: panel-save-shortcut
description: Panel Ctrl+S (backend.src.js) and Return in a text field: which form and button each submits, the button hint, disable_shortcut forms, buttons on their own form
---

## Which form does Ctrl+S submit on a panel screen?
- `backend.src.js`, the window `keydown` block, `case 's'`: the form around the focused element, skipping `form.disable_shortcut`; without one, the first visible such form that has a save button. It presses the form's `submitshortcut` button (default `submit_save`, found by `pgSaveShortcutButton()`); a form without one is never submitted.
- Before #3946 (fixed in PR #PR) it fell back to `form.submit()`, which skipped the button's click handlers and `data-confirm-content`: on Orders a bulk action refused with "No" (Delete included) ran, and a subscription was paused or cancelled without its question.
- On ready every form without `disable_shortcut` gets the hint `Ctrl+S | ⌘+S` in the title of that button only, added after a title it already has; no other button gets it.
- The Orders bulk buttons write the hidden `action` before their question; "No" now empties it again.
- The visitor page (`frontend.src.js`) has its own block, which only presses the inline editing Save button.

## Which forms carry `disable_shortcut`?
- Forms that only delete (the shortcut is too dangerous there) and helper forms that are never "the" form of a screen: the header search box, the account menu's quick form, `pg_filter_select()`'s filter, the licence check, the Site Settings dialog's form. Action forms: the Orders list (`view_orders.php`), each subscription action (`view_subscriptions.php`), the cart quotes list (`view_cart_quotes.php`). Forms that act at once: the Cloudflare Tools switches and Clear Cache (`cloudflare.php`); before, Ctrl+S on a fresh load submitted the first of them and flipped Development Mode on the live zone, and with focus on Clear Cache it purged the zone. A toggle redirects with `?focus=<switch id>` (and a custom period's dates), and that switch is drawn `autofocus`. The dashboard's widget settings, once a form in the Panel row's menu panel, are a card of Site Settings > Appearance now (`settings-dashboard-card.md`).
- A screen that answers Ctrl+S itself puts it on its form and catches the key: the visual editor (`#style_designer_form`).

## What if a dialog saves on Ctrl+S itself?
- It prevents the default and the shortcut leaves the key alone: `panel-save-shortcut-dialogs.md`.

## And Return in a text field?
- No script handles it: the browser submits the form with its first submit button. A screen whose first one is not Save runs that button on Return: on an image `edit_file.php` drew Rotate Left first, so Return in the name field turned the picture and dropped the new name (#1041, fixed in PR #1064).
- A button that does its own job (rotate, connect, align) goes on a form of its own: the button gets `form="<id>"`, the form sits outside the edit form with `class="disable_shortcut d-none"` and its own token. Examples: `edit_file.php` (`edit_file_rotate`), `search_console.php`, `smtp_settings.php`, `erp_account_sync_item.php`. Return then reaches the edit form's first button (Save & Return); Ctrl+S is unchanged.
- A form that keeps its action buttons (drawers) starts with a hidden nameless submit button instead; the guided tour's Enter: `return-key-default-button.md`.
- `edit_file.php` tells a submission from a page view by `$_POST['name']`, so its rotate form also posts the stored name.
