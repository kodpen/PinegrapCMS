---
name: erp-settings-cards
description: The ERP Settings cards drawn in two places (Accounting pane and erp_settings.php) from includes/erp/settings_cards.php; who sees which, how each saves, why the template editor stays on the screen.
---

## Why are the cards drawn in two places?
- Decision #3828 (A): Site Settings › Accounting shows the accounting rules, the late payment fee, the e-Fatura threshold and the document templates for role < 3. `erp_settings.php` keeps them for a role-3 user with `manage_erp_settings`, who has no Site Settings (`settings_pane.php` stays `manager`-only). No right changed.
- One code path: `includes/erp/settings_cards.php` draws the fields (`erp_settings_rules_fields()`, `erp_settings_late_fee_fields()`, `erp_settings_efatura_threshold_fields()`) and saves them (`erp_settings_cards_save($post, $cards)`); the pane and the screen only wrap them. Since #3840.

## How does each place save?
- The screen: one form per card, a hidden `rules` / `late_fee` / `efatura_threshold` flag, `erp_settings_cards_save($_POST, array($card))`, then back to `#erp_rules`, `#erp_late_fee`, `#erp_efatura_threshold` (System Status links the last one).
- The pane: the one Save. `accounting.save.php` checks `erp_settings_cards_refusals()` before any write, so a bad rate or code refuses the whole pane; then saves the cards whose fields came. The refusal checks repeat those of `erp_rules_save()`, `erp_late_fee_settings_save()` and `erp_efatura_threshold_save()`: change them together.
- A late fee or threshold change is logged only when a value changed (a pane save writes every card).

## Which cards are drawn when?
- Rules: always; the two VAT fields only with `erp_settings_rules_editable()` (Turkish features and the columns).
- Late fee: once its columns exist. Threshold: once its columns exist, hidden outside Turkey like E-Invoice, and left out of the list with it.
- Templates: in the pane a status list (saved or built-in) linking the screen's editor; the editor's Preview, Reset and document pills cannot ride one pane Save. So `erp_settings.php` does not redirect role < 3 to `#settings/accounting` yet.

## Where does the menu's ERP Settings entry lead?
- Role < 3: `pg_settings_link('accounting')`, without `data-pg-settings-page`, so `pgSettingsModal` opens the dialog on Accounting (#3929). A role-3 user with `manage_erp_settings` keeps `erp_settings.php`. The accountant's read-only right still hides the entry. `settings_accounting_test.php` reads both targets from `output_menu()`.
