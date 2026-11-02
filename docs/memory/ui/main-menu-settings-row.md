---
name: main-menu-settings-row
description: The retired Site Settings row of the main menu (slot 20): what replaced it, where Translations went, which screens it marked, what is left of data-pg-settings-page
---

## Is there a Site Settings row in the main menu?
- No. Slot 20 (its categories as first-level entries, each opening a panel of its sections) was removed on the owner's request; the System category keeps Users (8) and System tools (25). Like 3, 13, 18 and 19, slot 20 is never reused: operators pin rows by slot (`main-menu-categories.md`).
- `pg_menu_settings_entries()`, `pg_menu_settings_markup()` and `pg_settings_category_screens()` went with it, and the gear drawing of `pg_menu_icon()` (`main-menu-icons.md`). `tests/menu_panel_test.php` keeps the row retired.

## How are the settings reached now?
- The gear in the header opens the dialog (`settings-modal-entry-points.md`); links inside screens (`pg_settings_link()`) open it too. The pages `settings_<cat>.php` stay as the no-JavaScript path and the address of every such link, but no menu row leads to them and none is marked active on them.

## Where did Translations go?
- `translations.php` admits managers. Row 21 (Visual Page Editor) lists it for role < 2; a manager (role 2) has it in the Pages panel, right after the Visual Page Editor entry. Its active row follows: 21 below role 2, 2 for a manager (`main-menu-entry-gates.md`).

## What is left of data-pg-settings-page?
- `pgSettingsModal.asked()` (`backend.src.js`) still lets a link carrying `data-pg-settings-page` go to the settings page instead of opening the dialog. Nothing prints it since the row went; it stays as the hook for a link that wants the page.

Related: docs/memory/ui/main-menu-panels.md, docs/memory/ui/settings-system-tools.md
