---
name: ai-prompt-version
description: pg_ai_prompt_version() (r + 8 hex of the rules' SHA-1), ws_ai_requests.prompt_version (11.0.112, #2829), the insert helper and its scan test, where the version is shown
module: ai_prompts
---

## What is the rules' version?
- `pg_ai_prompt_version()`: `r` and the first eight hex digits of the SHA-1 of both profiles' lines; it changes by itself when a rule's text changes, and the text of a version is read from git history (`git log -S`), so no table keeps it (decided in #2829).

## Where is it kept (PR #2830)?
- `ws_ai_requests.prompt_version VARCHAR(16) NOT NULL DEFAULT ''`, step 11.0.112 of 2026.11.0. Every insert into the table takes `list($pv_column, $pv_value) = pg_ai_prompt_version_insert();` right before it and appends both: empty strings while the column is missing, so a site whose upgrade has not run keeps writing. tests/ai_prompt_rules_test.php scans the product and fails for an insert without it.
- Shown by `pg_ai_prompt_version_label()` ('' or a missing column reads Unknown): the Claude card's recent list (only once the column exists) and `prompt_version` (null when unknown) in the API's assistant requests.
- Write with AI keeps no request row: its Site Log line ends with "rules <version>" (server and browser model).
