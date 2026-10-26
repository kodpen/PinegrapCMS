---
name: settings-modal-architecture
description: Site Settings modal architecture: file map, the categories and their cards, moving a card, adding a new category.
---

## Why is Site Settings a modal and not a screen?
- A setting is almost always needed in the middle of another job; going to a page means abandoning it. The old single screen (5219 lines, one `<form>` writing 247 columns per save) was split into eight categories, which then moved into a modal.

## Which files make it up?
```
Modal (main path)
  includes/settings/modal.php      shell: header button + empty dialog + extras box
  settings_pane.php                endpoint: GET ?pane=<cat> cards, POST saves, both JSON
  includes/settings/fragment.php   pg_settings_fragment() / pg_settings_apply()
  assets/js/backend.src.js         pgSettingsModal, window.pgOpenSettings(), pgInitInjected()
Page (no-JavaScript path; linked from the main menu's Site Settings row, main-menu-settings-row.md)
  settings_<category>.php          thin wrappers, one per category: init + PG_SETTINGS_KEY + screen.php
  includes/settings/screen.php     page flow: shell, toolbar, save bar, POST/redirect
  settings2.php                    hub (deleted later)
  settings.php                     OLD single screen; orphan, in the Cleanup list
Shared
  includes/settings/registry.php   the ONE list: categories, sections, keywords, related screens
  includes/settings/prep.php       GET preparation (every category runs all of it)
  includes/settings/<cat>.php      cards -> $pg_settings_cards (+ $pg_settings_modals, $pg_settings_scripts)
  includes/settings/<cat>.save.php what that category writes
```

## Which categories and cards exist (27 sections)?
| Pane | Cards |
|---|---|
| `general` | server, software, channel, datetime, cron |
| `appearance` | theme, dashboard (role < 2 only, `settings-dashboard-card.md`), editor |
| `features` | features, images |
| `seo` | seo, analytics |
| `contact` | campaigns, chat, mailchimp, sms, sms-messages, iys |
| `security` | session, device, signin, membership |
| `firewall` | waf, bots, iplists |
| `commerce` | store, shipping, giftcards, invoice, payments, affiliate |

## How do you move a card?
- Two edits: the `sections` entry in `registry.php`, and moving the card block between `includes/settings/<key>.php` files.
- Column ownership follows the card: move the columns it writes between the `<key>.save.php` files too, otherwise the column is ownerless and never written.
- When moving to another category also add a row to `pg_settings_legacy_anchors()` (see settings-modal-entry-points).

## How do you add a new category?
- Entry in `registry.php` + `includes/settings/<key>.php` + `<key>.save.php` + `settings_<key>.php` wrapper (for the page path), and a `case 'settings_<key>.php'` in `output_menu()`'s switch so the main menu's Site Settings row is marked on it (`main-menu-settings-row.md`); the row's panels list the category by themselves.

## What does the page path do with a refused save?
- It redirects back and draws the cards from storage, so what was typed is lost there; the dialog keeps it (settings-invalid-vs-error). The cards are plain HTML: `liveform::prepare()` cannot refill them, it rebuilds every select from options the session does not hold.

Related: docs/memory/ui/settings-card-save-rules.md
