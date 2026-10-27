---
name: account-menu
description: Header account menu (pg_account_menu()): fixed card, phone sheet, why not hung from its button, ids other code needs, where the keyboard shortcuts went
---

## Where is it built?
- `pg_account_menu($user, $options)` in `includes/fn/output.php`, called by `output_header()`; it returns the `<li class="nav-item pg-menu-wrap">` with the button (`#user_menu_toggle.pg-acctbtn`, `data-bs-toggle="menu"`, `data-bs-placement="bottom-end"`, `data-bs-display="static"`, `data-bs-auto-close="outside"`) and the menu (`ul#user_menu.menu.pg-menu-end.pg-usermenu.backdrop`). Styles: `backend.src.css` "Account menu"; script: `pg_account_menu_init()` and `pg_contact_quick_init()` in `backend.src.js`.
- Order: who is signed in (an administrator's links to `edit_user.php`), the address book form while the name or picture is missing, the licence card (`license_check('bar')`), appearance, My Account / connected applications / calendar subscription / start page, Help & Support, logout, version line.

## Why is it fixed to the viewport?
- Bootstrap 5 gave no Popper to a dropdown inside a `.navbar`; under Bootstrap 6 the toggle's `data-bs-display="static"` likewise skips Floating UI. A menu hung from its toggle has nothing keeping it on screen. The old menu ran off the left edge of a phone, and could not scroll: `#header .pg-usermenu {overflow:hidden}` beat `#user_menu {overflow-y:auto}` on specificity, so on a phone logout and the rows below the fold were out of reach (rebuilt in #593).
- Now: from 576px a 20rem card under the bar's right edge, `max-height` from `100dvh`, scrolling inside with `overscroll-behavior: contain`. Below 576px a sheet anchored to all four edges under the bar, solid even with the acrylic effect, with its own close button (`[data-pg-account-close]`); the page behind does not scroll and the chat bubble steps aside while it is open.
- Opening any header menu closes the main menu drawer (`initMenuDrawer()`), which is drawn above the header's layer.

## Which ids and classes does other code rely on?
- `.pg-acctbtn`: the frame tour's account step and the tour link's handler (`includes/fn/tour.php`). `#tour_link`: plays that tour. `#help_link a`: F1's fallback. `#theme-light|dark|auto` with `data-bs-theme-value`: the theme switch in `backend.src.js`. `#pg_contact_quick` with `data-pg-contact-quick`: the address book form (texts and the api address in `data-*`).
- Menu rows carry no colour, logout included (`admin-screen-toolbar-rules.md`), and no `title` (every titled element gets a hover popover).

## Where did the keyboard shortcuts go?
- Out of the menu (nobody found the accordion). `pg_shortcuts_dialog()` prints `#pg_shortcuts` on every screen; `?` outside a field opens it, and the search box offers a "Keyboard Shortcuts" chip (`command-palette.md`). On Apple devices the `kbd[data-pg-mod]` keys read ⌘.
