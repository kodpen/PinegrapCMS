---
name: settings-email-tabs
description: Site Settings › E-mail (EM1): one category, tabs E-mail sending and Mailboxes under their old gates, the tab kept in the address, old keys, pages and anchors opening their tab, the save per tab
module: email_settings_tabs
---

## What is the category?
- Architect (2026-11-21, agent channel message 11313): key `email`, label "E-mail", tabs kept in the address, each tab keeping today's gate; one right shows no tab control. Built by EM1 (#10983).
- `pg_settings_email_category()` (`includes/settings/registry.php`, read without the database): sections and keywords of both tabs, `tabs` => sending (gate '' = manager, module `mail_sending`, reads `prep.php`) and mailboxes (gate `mail`, module `mailboxes`). `lists`: `ws_mail` 3.
- Tab label "E-mail sending": the key "Sending" is the mail queue's status in Turkish.

## How are the gates asked?
- `pg_settings_gate_open()`; `pg_settings_category_open()` opens a category with tabs when one tab opens (`pg_settings_tabs_open()`).
- `pg_settings_categories_for()` and the page and the fragment narrow it to the reader (`pg_settings_category_for_reader()`): only the open tabs and their sections and keywords, so the list, the search, the chips and the palette never name the other tab's cards. `pg_settings_category_reads_prep()` reads `prep.php` only with the Sending tab.
- `pg_settings_may_open('mail_sending'|'mailboxes', …)` asks that tab's gate (`pg_settings_moved_categories()`); Connections' Mailboxes card uses it.

## How is it drawn?
- `includes/settings/email.php` includes each open tab's module, then the tab control (`pg_email_tabs_html()`, the Design examples' "Tabs with a state", `.pg-seg.pg-seg-tall`) with each tab's pill, and each tab's cards in `div.pg-settings-body#pgset-email-<tab>[data-pg-set-tabpanel]` (`pg_email_tab_panel_html()`), with a hidden `email_<tab>_tab`.
- Pills: Sending (`pg_email_tab_sending_state()`): Not set up (no way to send), Check the domain (SPF, DKIM or DMARC warning or error, from the Sending domain card's lookup, `$mail_domain_health`), Ready. Mailboxes (`pg_email_tab_mailboxes_state()`): No box, Error, Sign-in needed, "{n} reading", Off.
- The tabs and the reveal live in `backend.src.js` (`[data-pg-set-tab]`, `pgSettingsTabReveal()`, `pgSettingsTabsInit()`); `pgSettingsFoldReveal()` reveals the tab first, so every way into a card (anchor, search hit, refused field) opens its tab.

## How is the tab kept in the address?
- Dialog: `#settings/email/pgset-email-<tab>`, written on a tab change; after a save the pane's script reopens the tab the address names.
- Page: `settings_email.php#pgset-email-<tab>`; the form's action takes the same fragment, so the save's redirect returns to the tab. `settings_mail_sending.php` and `settings_mailboxes.php` keep their keys; `screen.php` turns them into `email` on their tab and posts back to them.
- Old keys in the dialog: `data-pgmoved` (`modal.php`); `open()` and `fromUrl()` turn `mail_sending` / `mailboxes`, their pages and `#settings/<old>/…` into `email` on the tab. Old anchors (`pgset-campaigns`, `mail_transport_card`, `mail_domain_card`, `ws-mail`) point at `email`; `pgset-mailchimp` opens Mailchimp's page of Connections (`data-pgaway`, `connections-mailchimp-page.md`). The registry's mail rows name `#settings/email/…`.

## What does a save write?
- `email.save.php`: `mail_sending.save.php` only when the reader opens the Sending tab and `email_sending_tab` was posted (it writes every field of its cards; Bring in an .eml file posts its own form to the Mailboxes page), then `mailboxes.save.php` (acts only on what it finds) unless the first refused. The audit line is `email`; old `mail_sending` / `mailboxes` lines are named "E-mail › <tab>" (`pg_audit_view_settings_names()`).

Tests: `tests/settings_email_tabs_test.php`, `tests/settings_workspace_gate_test.php`.

Related: docs/memory/ui/settings-mail.md, docs/memory/ui/settings-mail-sending.md, docs/memory/workspace/workspace-mail-settings-dialog.md
