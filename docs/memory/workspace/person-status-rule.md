---
name: person-status-rule
description: A person's own status (PS1): ws_profiles.status (11.0.290), the pure rule, ws_person_status_*(), GET/POST /workspace/status as MCP tools, status on the person brief
module: person_status
---

## Where is it kept?
- `ws_profiles.status` TEXT NULL, added by step 11.0.290 (`upgrade_2026_11_0_ws_person_status()`): JSON `kind`, `note`, `until` (Unix time, 0 no end), `at` (when set). NULL is no status; no backfill. Decision: `workflow/decisions-made-schema-57.md`.
- `ws_person_status_ready()` probes the column; before it nothing is read, and `ws_people_select()` adds `p.status` only once it exists (a missing column would make every people query return false).

## Why ws_person_status_* and not ws_status_*?
- `ws_status_*` (`ready`, `facts`, `post`, `action`…) is the channel's status updates and check-ins (`includes/workspace/status.php`); `ws_status_ready()` already existed there. Every name of a person's status carries `person_status`: functions, the panel action `ws_person_status_save`, `api_person_status.php`, objects `WorkspacePersonStatus` / `WorkspacePersonStatusValue`.

## What does the rule say? (`includes/fn/person_status.php`, pure)
- Kinds in order: `available` "Available to talk", `busy`, `meeting` "In a meeting", `out` "Out of office", `sleeping`, `leave` "On leave". "Available" and "Out" were taken in the Turkish files with other meanings (in stock, exit), hence the longer English labels.
- Note: one line (breaks and control characters become spaces), at most 80 characters, counted as characters.
- Ends: `none`, `30m`, `1h`, `4h`, `today` (the coming midnight), `morning` (the end of the person's quiet hours, else 08:00), `at` with `until` (Unix time or ISO date-time, in the person's zone without an offset, within 366 days). No end given takes the kind's default: busy and meeting 1h, out 4h, sleeping morning, leave today, available none.
- `pg_person_status_decode($value, $now)` drops a broken value, an unknown kind and a status whose `until` has passed: an ended status reads as none and no job clears it.
- Words of an end in the person's zone (`user.timezone`, else the site's): "until 18:00 today", "until the end of today", "until tomorrow 08:00", "until Friday 18:00" within the week, else "until 2026-12-24 18:00". "until {var:1}" was taken for a date, hence "today".
- Kind empty clears the status.

## Who sets it?
- Only the person: `pg_person_status_save_check()` refuses a non-member and a `user_id` naming somebody else. `ws_person_status_save()` is called only by the panel action `ws_person_status_save` and `POST /workspace/status` (`tests/person_status_test.php`).
- `GET` and `POST /workspace/status` (`workspace:read` / `workspace:write`) start with `ws_api_person_only()`: the team devices session and the person's own OAuth connection pass, an application key gets 403. Both are MCP tools (no `'mcp' => false`). The write rehearses: check, `api_dry_run_stop()`, write. Bad field 422 on `kind`, `note`, `end` or `until`; no column 503.

## What does a brief carry?
- `ws_person_brief()` gains `status`: null, or `kind`, `label`, `note`, `until`, `until_words`, `at` (`pg_person_status_present()`). The API's member objects and the person card do not carry it yet (PS3).
