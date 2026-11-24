---
name: lead-score-settings
description: The interest score's settings row under Site Settings › Visitors and privacy (LJ3b, #11245): its fields, the save into config_text lead_score, Back to the defaults; the contacts list's Score column
module: lead_score
---

## Where are the interest score's settings set?
- Set on Site Settings › Visitors and privacy, row "Interest score" (`pgset-interest-score`, after Cookie consent; no Contacts category exists), drawn once `contact_scores` exists (LJ3b, #11245). Checked by `pg_lead_score_settings_input()`, stored by `pg_lead_score_settings_text()`: the defaults store nothing (row deleted). Written only when the card posted `lead_score_card`.
- "Back to the defaults" only fills the fields from `data-pg-default`; the pane's Save writes them.
- Fields: "Hot from" (`lead_score_threshold`), "What counts" as one row an act with its points (`lead_score_weight_<kind>`, 0 to 100), "Pricing pages" and "Contact pages" (`<select multiple data-pg-combobox>` of every page, ids), under "Advanced": "Points halve in" days, "Keep the journey for" months. Bounds as `visitors/lead-score.md`.
- Pages are a multiple combobox because the page picker (`pg_picker_field()`) holds one value.

## How is the score shown on the contacts list?
- Contacts list: column "Score" (`score`, sorts by `contact_scores.interest`), hidden until turned on in the columns menu; main views only, not the address views or duplicates (`pg_contact_list_shows_scores()`, joined in `pg_contact_list_source()`). The figure, with a "Hot" pill from the threshold on; empty when not scored.

Tests: `tests/lead_score_test.php`, `tests/contact_list_driver_test.php`, `tests/settings_privacy_fold_test.php`.
Related: docs/memory/visitors/lead-score.md, docs/memory/ui/settings-privacy.md
