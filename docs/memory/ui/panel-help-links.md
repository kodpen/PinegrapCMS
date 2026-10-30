---
name: panel-help-links
description: Where a panel screen's help goes: get_help_url.php maps script names (and list filters) to page#anchor on pinegrap.com; the 'help' property, language prefix, private label, F1, adding a new screen.
---

## Where does help for a screen point?
- To the documentation on pinegrap.com: English at the root (`https://pinegrap.com/<page>#<anchor>`), other languages under a prefix (`tr/`). The panel language picks the prefix; a language the documentation is not written in gets English. A topic is written once as `page#anchor`, the same in every language.
- `get_help_url.php` holds the table: script name without `.php` -> `page#anchor`; a list whose topic changes with `?filter=` maps to an array of filter -> topic (`''` = no filter). A screen missing from the table gets `introduction`, never nothing.
- Read it through `pg_help_url()` (`includes/fn/output.php`), which returns `''` on a private-labelled site: no help control is drawn there.

## What does a new screen need?
- A row in the table: its script name -> `page#anchor` (the anchor from the page's table of contents).
- A screen whose subject depends on more than its script name passes `'help' => 'page#anchor'` to `output_header()` / `pg_page_shell()`. A topic that is not `[a-z0-9_-]+(#[a-z0-9_-]+)?` falls back to `introduction`.
- `tests/help_url_test.php` guards it: every root `*.php` that calls `pg_page_shell(` or `output_header(` needs a table row, a literal `'help' => 'page#anchor'` argument, or an entry with a reason in the test's short exempt list (popup, redirect-only, private screen). A row whose anchor pinegrap.com does not have yet is fine: the owner writes the section (the PR body proposes it), the link lands on the page top until then.
- The header's help button (`#help_button`), the account menu's Help row (`#help_link`) and F1 (`backend.src.js`, opens a new tab) use that one address; never write a help link of your own. While the settings dialog is open, F1 opens the help of the category on view (`#pg_settings_modal_help`). The open-state test is `#pg_settings_modal[open]:not(.hiding)` (as `pgUI.isOpen`): a Bootstrap 6 `<dialog>` never gets `.show`, and while the selector used `.show` F1 opened the help of the screen behind the dialog (or nothing); pinned by `tests/settings_help_f1_test.php`.

Related: docs/memory/ui/account-menu.md, docs/memory/ui/admin-screen-toolbar.md
