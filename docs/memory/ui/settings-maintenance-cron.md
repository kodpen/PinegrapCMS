---
name: settings-maintenance-cron
description: Site Settings › Updates and maintenance, the Scheduled jobs row opened: the general job's command line, one row a job with its switch, a numbered steps drawer a job
module: maintenance_cron_list
---

## How is the Scheduled jobs row drawn open?
- Card `pgset-cron`: the group General job with its command on one read-only line (`<code>` + Copy, `pg_copy_button()`), "Every minute", the OS family; the switch "Run the jobs with the general job" (`job_dispatch_enabled`); the group Jobs with one row a job.
- A row: name (a `<label>` of the job's switch, so the settings search finds the card by a job's name: #12036), one line of what it does, "Recommended: once a day" (an inline job: "With every run of the general job"), the ghost button "How to schedule it" and the job's switch `job_dispatch_job[<name>]`, hidden with `d-none` while the master is off, never disabled, so the selection stays posted.
- The rows are every `pg_cron_jobs()` entry with `dispatch` true, in the catalogue's order; a job config.php switched off says "disabled in config.php".

## Where is it built?
- `includes/fn/maintenance_cron_list.php`: `pg_maintenance_cron_command()` (Windows php.exe form, every other family the unix form), `pg_maintenance_cron_job_line()`, `pg_maintenance_cron_rows()`, `pg_maintenance_cron_steps()` / `_notes()`, `pg_maintenance_cron_list_html()`, `pg_maintenance_cron_guide_drawer()`. Test: `tests/settings_maintenance_cron_test.php`, which fails when a new dispatchable job has no line.
- A new job in `pg_cron_jobs()` needs a case in `pg_maintenance_cron_job_line()`, one sentence of at most 110 characters.

## What does a job's drawer hold?
- Id `pgset-cron-<name with hyphens>-guide`, printed through `$pg_settings_modals`, the stepper of the assistants' guides (`ui/settings-assistants-help-lines.md`): an inline job first "Usually not needed"; then open the scheduled tasks (Task Scheduler on Windows, the hosting panel's cron or `crontab -e`), add this command with Copy, its schedule, the job's notes as short steps, "Or leave it to the general job".
- A missing PHP extension (pdo_mysql for Auto backup, DOM for SEO structure) is a warning at the drawer's top.

## What went with it?
- The drawer `cron_jobs` "Setup Instructions" (eleven jobs, a paragraph each, a code editor a command) and the `$cron_job_*`, `$output_job_dispatch_switches` and `$output_warnings_for_*` variables of `includes/settings/prep.php`. The save (`maintenance.save.php`) and the field names are unchanged.

Related: docs/memory/ui/settings-maintenance.md, docs/memory/platform/scheduled-job-resume.md
