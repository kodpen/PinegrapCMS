---
name: import-campaign-profiles-screen
description: import_email_campaign_profiles.php on the wide edit layout (step B11): the Import CSV and File Format cards, the Summary with the name-match warning, the save bar
---

## How is the Import Campaign Profiles screen laid out?
- The product_builder pattern (`edit-screen-wide-layout.md`): the form column holds Import CSV (file box, upload limit hint) and File Format (the columns the import reads, in the export's order; the export's reading columns are skipped); the sticky status column holds a Summary (the chosen file's name, set by a small script) and the warning that a matching name updates the stored profile; the sticky save bar holds Import.
- The Summary shows no profile count: a basic user imports only their own followed profiles, so a count of every profile would say more than the list shows them.
- The import itself (headings, checks, `pg_email_campaign_profile_values_problem()`) is unchanged: `email-campaign-profile-row-rules.md`.
