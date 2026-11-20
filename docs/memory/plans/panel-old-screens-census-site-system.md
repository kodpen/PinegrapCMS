---
name: panel-old-screens-census-site-system
description: Census LM1b against look A: visitors and logs (S4: Site Log, Audit Trail, Firewall log, Performance, Search Console), System tools, Translations, settings pages outside the dialog (S6)
---

## How is a line read?
- `file`: lines / marker lines / `style=` / fields / cards → estimate; method in `panel-old-screens-census-site.md`.

## Which visitor and log screens, and what is old? (320; 3,650 lines, in S4)
- Visitor `view_visitor.php` 330 / 7 / 1 / 0 / 6 → 31: no measure class (`ui/look-screen-widths.md` leaves it to the field-width work), upper-case titles. Visitor reports list `view_visitor_reports.php` → 13; the report itself is done (DL5e).
- Site Log `view_log.php` 830 / 10 / 1 / 9 / 0 → 40 (six selects, a 22-line `<style>`); Audit Trail `view_audit_log.php` 299 / 10 / 0 / 6 / 0 → 34.
- Firewall log `view_waf_log.php` 735 / 34 / 3 / 4 / 12 → 78: `text-uppercase h6 fg-primary fw-bold` titles, `small text-uppercase` labels, three own tables.
- Performance `view_performance_log.php` 663 / 30 / 2 / 3 / 10 → 68: not on the table driver (#9230), three own `table table-hover` tables.
- Search Console `search_console.php` 662 / 30 / 3 / 1 / 13 → 58: on the report width, but five upper-case card titles and an own table.

## Which tools, and what is old? (445; 8,500 lines, in S6)
- Translations `translations.php` 1,027 / 70 / 4 / 7 / 10 → 116, the oldest here: 22 Bootstrap buttons, its own toolbar and dialogs, an 11-line `<style>`.
- Database engine `database_engine.php` 1,415 / 34 / 0 / 8 / 14 → 78: six `text-uppercase h5 fg-primary fw-bold` card headers, two own tables.
- Find and Replace `find_and_replace.php` 1,017 → 49; Mass Edit `mass_edit.php` 811 → 45; Repair Software `software_repair.php` 440 → 30 (no measure class); Backups `backups.php` 451 → 29 (not on the driver, #9230); Experiments `experiments.php` 297 → 25 (three own tables); Import `import.php` 762 → 23.
- Small: `migration.php` → 17, `api_docs.php` → 13, `clean_up.php` → 8, `developer_lock.php` → 5, `software_update.php` → 5 (opens in the settings dialog); `system_tools.php` clean.
- S6c done in #10547 (`ui/look-translations-screen.md`): Translations. `clean_up.php` is left to the plan's close-out: open PR #10528 changes it.

## Which settings pages outside the dialog, and what is old? (380; 5,900 lines, in S6)
- API `api_settings.php` 2,653 / 92 / 6 / 11 / 8 → 130: 18 Bootstrap buttons, `small text-uppercase` labels, a 116-line `<style>` with 24 font or colour literals.
- Cloudflare `cloudflare.php` 1,482 / 41 / 1 / 32 / 14 → 133: no measure class (it opens in the settings dialog), `fs-xl fw-bold` figures, a striped table, upper-case titles.
- SMTP `smtp_settings.php` 726 → 59 (16 fields); `connected_apps.php` → 12; `device_pairing.php` → 11.
- Connected accounts `connections.php` 595 → 37, still counted in the figures above: not S6's, it was redrawn once as the Connections screen, #10200 and #10237 (architect 2026-11-17, agent channel message 7524; `ui/connections-screen-layout.md`).
